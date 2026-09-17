<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MembershipPaymentService
{
    /**
     * Build package fee totals.
     * Package price = amount paid + discount + balance due.
     * Explicit discount/balance win; otherwise underpay remainder becomes discount (legacy).
     *
     * @return array{subtotal:float,tax:float,discount:float,balance:float,total:float,amount_paid:float}
     */
    public function totalsForPackagePayment(
        MembershipPlan $plan,
        float $amountPaid,
        float $tax = 0,
        bool $applyUnderpayDiscount = true,
        ?float $explicitDiscount = null,
        ?float $explicitBalance = null,
    ): array {
        $subtotal = round((float) $plan->price, 2);
        $tax = max(0, round($tax, 2));

        if ($explicitDiscount !== null || $explicitBalance !== null) {
            $discount = min($subtotal, max(0, round((float) ($explicitDiscount ?? 0), 2)));
            $balance = min(
                max(0, $subtotal - $discount),
                max(0, round((float) ($explicitBalance ?? 0), 2))
            );
            $amountPaid = round(max(0, $subtotal - $discount - $balance), 2);
        } else {
            $amountPaid = max(0, round($amountPaid, 2));
            $discount = ($applyUnderpayDiscount && $amountPaid < $subtotal)
                ? round($subtotal - $amountPaid, 2)
                : 0.0;
            $balance = 0.0;
            if (! $applyUnderpayDiscount && $amountPaid < $subtotal) {
                $balance = round($subtotal - $amountPaid, 2);
            }
        }

        $total = max(0, round($subtotal + $tax - $discount, 2));

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'balance' => $balance,
            'total' => $total,
            'amount_paid' => $amountPaid,
        ];
    }

    /**
     * Pick best catalog package for a paid amount.
     */
    public function matchPackageForAmount(float $amountPaid, ?Collection $plans = null): ?MembershipPlan
    {
        $plans = ($plans ?? MembershipPlan::where('is_active', true)->get())
            ->sortBy(function (MembershipPlan $plan) {
                $order = collect(config('membership_packages', []))
                    ->map(fn ($r) => Str::slug($r['name']))
                    ->values()
                    ->all();
                $idx = array_search($plan->slug, $order, true);

                return $idx === false ? 1000 + (float) $plan->price : $idx;
            })
            ->values();

        if ($plans->isEmpty()) {
            return null;
        }

        $amount = round($amountPaid, 2);

        $exact = $plans->first(fn (MembershipPlan $p) => round((float) $p->price, 2) === $amount);
        if ($exact) {
            return $exact;
        }

        $higherOrEqual = $plans
            ->filter(fn (MembershipPlan $p) => (float) $p->price >= $amount)
            ->sortBy(fn (MembershipPlan $p) => (float) $p->price)
            ->values();

        if ($higherOrEqual->isNotEmpty()) {
            return $higherOrEqual->first();
        }

        return $plans->sortByDesc(fn (MembershipPlan $p) => (float) $p->price)->first();
    }

    /**
     * Record package fee as Payment only and sync subscription period.
     *
     * @return array{payment:Payment,discount:float,balance:float,skipped?:bool}
     */
    public function recordPackageFee(
        Member $member,
        MembershipPlan $plan,
        float $amountPaid,
        ?string $feeStart = null,
        ?string $feeEnd = null,
        string $subscriptionStatus = 'active',
        ?float $explicitDiscount = null,
        ?string $idempotencyKey = null,
        ?int $accountId = null,
        string $method = 'cash',
        ?string $paymentDate = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $receivedBy = null,
        bool $applyUnderpayDiscount = true,
        ?float $explicitBalance = null,
    ): array {
        $totals = $this->totalsForPackagePayment(
            $plan,
            $amountPaid,
            0,
            $applyUnderpayDiscount,
            $explicitDiscount,
            $explicitBalance
        );

        $cashAmount = max(0, (float) $totals['amount_paid']);
        $start = $feeStart ?: now()->toDateString();
        $end = $feeEnd ?: Carbon::parse($start)->addDays((int) $plan->duration_days)->toDateString();
        $payDate = $paymentDate ?: $start;
        $accountId = $accountId ?: $this->defaultCashAccountId();

        $splitNotes = [];
        if ($totals['discount'] > 0) {
            $splitNotes[] = 'Package discount ' . number_format($totals['discount'], 2);
        }
        if ($totals['balance'] > 0) {
            $splitNotes[] = 'Balance due ' . number_format($totals['balance'], 2);
        }
        if ($totals['discount'] > 0 || $totals['balance'] > 0) {
            $splitNotes[] = '(paid ' . number_format($cashAmount, 2)
                . ' of ' . number_format($totals['subtotal'], 2) . ')';
        }
        $finalNotes = trim(implode("\n", array_filter([
            $notes,
            $splitNotes ? implode(' · ', $splitNotes) : null,
        ])));

        if ($idempotencyKey) {
            $existing = Payment::where('reference', $idempotencyKey)->first();
            if ($existing) {
                $oldAmount = (float) $existing->amount;
                $delta = $cashAmount - $oldAmount;

                $existing->update([
                    'membership_plan_id' => $plan->id,
                    'amount' => max($cashAmount, 0.01),
                    'discount' => $totals['discount'],
                    'balance' => $totals['balance'],
                    'payment_date' => $payDate,
                    'fee_start_date' => $start,
                    'fee_end_date' => $end,
                    'account_id' => $accountId ?: $existing->account_id,
                    'status' => 'completed',
                    'notes' => $finalNotes ?: $existing->notes,
                    'invoice_id' => null,
                ]);

                $this->syncSubscription(
                    $member,
                    $plan,
                    $cashAmount,
                    $start,
                    $end,
                    $subscriptionStatus,
                    (float) $totals['balance']
                );

                if ($accountId && abs($delta) > 0.00001) {
                    $this->adjustAccountBalance($accountId, $delta);
                }

                return [
                    'payment' => $existing->fresh(['plan', 'member']),
                    'discount' => (float) $totals['discount'],
                    'balance' => (float) $totals['balance'],
                    'skipped' => true,
                ];
            }
        }

        $payment = Payment::create([
            'payment_number' => $this->generatePaymentNumber(),
            'invoice_id' => null,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'account_id' => $accountId,
            'amount' => max($cashAmount, 0.01),
            'discount' => $totals['discount'],
            'balance' => $totals['balance'],
            'method' => $method,
            'payment_date' => $payDate,
            'fee_start_date' => $start,
            'fee_end_date' => $end,
            'reference' => $reference ?: $idempotencyKey,
            'status' => 'completed',
            'notes' => $finalNotes ?: null,
            'received_by' => $receivedBy ?? auth()->id(),
        ]);

        $this->syncSubscription(
            $member,
            $plan,
            $cashAmount,
            $start,
            $end,
            $subscriptionStatus,
            (float) $totals['balance']
        );

        if ($accountId && (float) $payment->amount > 0) {
            $this->adjustAccountBalance($accountId, (float) $payment->amount);
        }

        return [
            'payment' => $payment->fresh(['plan', 'member']),
            'discount' => (float) $totals['discount'],
            'balance' => (float) $totals['balance'],
            'skipped' => false,
        ];
    }

    /**
     * Attach package + fee period to an existing payment (Payments UI create flow).
     */
    public function applyPackageToPayment(
        Payment $payment,
        Member $member,
        MembershipPlan $plan,
        ?string $feeStart = null,
        ?string $feeEnd = null,
        bool $applyUnderpayDiscount = true,
        ?float $explicitDiscount = null,
        string $subscriptionStatus = 'active',
    ): Payment {
        $totals = $this->totalsForPackagePayment(
            $plan,
            (float) $payment->amount,
            0,
            $applyUnderpayDiscount,
            $explicitDiscount
        );

        $start = $feeStart ?: ($payment->payment_date?->toDateString() ?: now()->toDateString());
        $end = $feeEnd ?: Carbon::parse($start)->addDays((int) $plan->duration_days)->toDateString();

        $notes = $payment->notes;
        if ($totals['discount'] > 0) {
            $discountNote = 'Package discount ' . number_format($totals['discount'], 2)
                . ' (paid ' . number_format((float) $payment->amount, 2)
                . ' of ' . number_format($totals['subtotal'], 2) . ')';
            $notes = trim(($notes ? $notes . "\n" : '') . $discountNote);
        }

        $payment->update([
            'invoice_id' => null,
            'membership_plan_id' => $plan->id,
            'discount' => $totals['discount'],
            'balance' => $totals['balance'] ?? 0,
            'fee_start_date' => $start,
            'fee_end_date' => $end,
            'notes' => $notes,
        ]);

        $this->syncSubscription(
            $member,
            $plan,
            (float) $totals['amount_paid'],
            $start,
            $end,
            $subscriptionStatus,
            (float) ($totals['balance'] ?? 0)
        );

        return $payment->fresh(['plan', 'member']);
    }

    public function syncSubscription(
        Member $member,
        MembershipPlan $plan,
        float $amountPaid,
        ?string $feeStart = null,
        ?string $feeEnd = null,
        string $status = 'active',
        float $balanceDue = 0,
    ): MemberSubscription {
        $start = $feeStart ?: now()->toDateString();
        $end = $feeEnd;
        if (! $end) {
            $end = Carbon::parse($start)->addDays((int) $plan->duration_days)->toDateString();
        }

        $payload = [
            'membership_plan_id' => $plan->id,
            'start_date' => $start,
            'end_date' => $end,
            'amount_paid' => $amountPaid,
            'balance_due' => max(0, round($balanceDue, 2)),
            'status' => $status,
        ];

        $active = $member->subscriptions()->where('status', 'active')->latest('id')->first();
        if ($active) {
            $active->update($payload);

            return $active->fresh();
        }

        return $member->subscriptions()->create($payload);
    }

    /**
     * Assign package on subscription (no payment). Returns discount vs package price.
     */
    public function assignPackageToSubscription(MemberSubscription $sub, ?MembershipPlan $plan = null): array
    {
        $amount = (float) $sub->amount_paid;
        $plan = $plan ?: $this->matchPackageForAmount($amount);
        if (! $plan) {
            return ['ok' => false, 'discount' => 0, 'plan' => null];
        }

        $discount = max(0, round((float) $plan->price - $amount, 2));
        $sub->update(['membership_plan_id' => $plan->id]);

        return ['ok' => true, 'discount' => $discount, 'plan' => $plan];
    }

    public function defaultCashAccountId(): ?int
    {
        return Account::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'CASH-001')->orWhere('name', 'like', '%Cash%');
            })
            ->value('id');
    }

    private function adjustAccountBalance(int $accountId, float $delta): void
    {
        if ($delta == 0.0) {
            return;
        }

        $account = Account::find($accountId);
        if ($account) {
            $account->update([
                'current_balance' => (float) $account->current_balance + $delta,
            ]);
        }
    }

    private function generatePaymentNumber(): string
    {
        do {
            $number = 'PAY-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Payment::where('payment_number', $number)->exists());

        return $number;
    }
}
