<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\MembershipPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberPackageSetupController extends Controller
{
    public function __construct(
        private readonly MembershipPaymentService $membershipPayments,
    ) {
    }

    public function index(Request $request): View
    {
        $member = null;
        $packageHistory = collect();

        if ($request->filled('member_id')) {
            $member = Member::with([
                'trainer',
                'activeSubscription.plan',
                'subscriptions' => fn ($q) => $q->with('plan')->orderByDesc('end_date')->orderByDesc('id'),
                'payments' => fn ($q) => $q->with('plan')->latest('payment_date')->latest('id')->limit(50),
            ])->find($request->integer('member_id'));

            if ($member) {
                $packageHistory = $this->buildPackageHistory($member);
            }
        }

        return view('members.package-setup', [
            'member' => $member,
            'packageHistory' => $packageHistory,
            'plans' => $this->activePackages(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'defaultAccountId' => $this->membershipPayments->defaultCashAccountId(),
        ]);
    }

    /**
     * Build full package-period history from fee payments (and any subscription-only rows).
     * Past renewals live on payments even when the active subscription row was overwritten.
     */
    private function buildPackageHistory(Member $member)
    {
        $today = now()->startOfDay();
        $active = $member->activeSubscription;
        $rows = collect();

        $feePayments = Payment::query()
            ->with('plan')
            ->where('member_id', $member->id)
            ->whereNotNull('fee_start_date')
            ->whereNotNull('membership_plan_id')
            ->orderByDesc('fee_start_date')
            ->orderByDesc('id')
            ->get();

        foreach ($feePayments as $payment) {
            $start = optional($payment->fee_start_date)->toDateString();
            $end = optional($payment->fee_end_date)->toDateString();
            $key = $start.'|'.$end.'|'.$payment->membership_plan_id;
            if ($rows->has($key)) {
                continue;
            }

            $isActive = $active
                && optional($active->start_date)->toDateString() === $start
                && optional($active->end_date)->toDateString() === $end
                && (int) $active->membership_plan_id === (int) $payment->membership_plan_id;

            $status = 'completed';
            if ($isActive) {
                $status = 'active';
            } elseif ($payment->fee_end_date && $payment->fee_end_date->copy()->startOfDay()->lt($today)) {
                $status = 'expired';
            }

            $rows->put($key, (object) [
                'plan_name' => $payment->plan?->name ?? '—',
                'start_date' => $payment->fee_start_date,
                'end_date' => $payment->fee_end_date,
                'amount_paid' => (float) $payment->amount,
                'balance_due' => (float) ($payment->balance ?? 0),
                'status' => $status,
            ]);
        }

        foreach ($member->subscriptions as $sub) {
            $start = optional($sub->start_date)->toDateString();
            $end = optional($sub->end_date)->toDateString();
            $key = $start.'|'.$end.'|'.$sub->membership_plan_id;
            if ($rows->has($key)) {
                continue;
            }

            $status = $sub->status;
            if ($status === 'active' && $sub->end_date && $sub->end_date->copy()->startOfDay()->lt($today)) {
                $status = 'expired';
            } elseif ($status === 'cancelled' && $sub->end_date && $sub->end_date->copy()->startOfDay()->lt($today)) {
                $status = 'expired';
            }

            $rows->put($key, (object) [
                'plan_name' => $sub->plan?->name ?? '—',
                'start_date' => $sub->start_date,
                'end_date' => $sub->end_date,
                'amount_paid' => (float) $sub->amount_paid,
                'balance_due' => (float) ($sub->balance_due ?? 0),
                'status' => $status,
            ]);
        }

        return $rows->values()->sortByDesc(function ($row) {
            return optional($row->start_date)->timestamp ?? 0;
        })->values();
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['members' => []]);
        }

        $members = Member::query()
            ->with('activeSubscription.plan')
            ->where(function ($query) use ($q) {
                $query->where('device_user_id', 'like', "%{$q}%")
                    ->orWhere('member_code', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) like ?", ["%{$q}%"]);
            })
            ->orderByRaw("CAST(NULLIF(device_user_id, '') AS UNSIGNED) DESC")
            ->limit(20)
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->full_name,
                'bio_id' => $m->device_user_id ?: '',
                'code' => $m->member_code,
                'phone' => $m->phone,
                'package' => $m->activeSubscription?->plan?->name,
            ]);

        return response()->json(['members' => $members]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'membership_plan_id' => ['required', 'exists:membership_plans,id'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'fee_expiry_date' => ['required', 'date', 'after_or_equal:effective_date'],
            'change_type' => ['required', 'in:new,renew,upgrade,downgrade,transfer'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'record_payment' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'cheque_no' => ['nullable', 'string', 'max:100'],
            'balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        $start = $data['effective_date'];
        $end = $data['fee_expiry_date']
            ?: ($member->feeExpiryForStart($start)
                ?: Carbon::parse($start)->addDays((int) $plan->duration_days)->toDateString());
        // Permanent monthly due day = day of the expiry date they set.
        $dueDay = (int) Carbon::parse($end)->day;
        $monthlyFee = round((float) $data['monthly_fee'], 2);
        $recordPayment = $request->boolean('record_payment');
        $paymentAmount = round((float) ($data['payment_amount'] ?? 0), 2);
        $balance = array_key_exists('balance', $data) && $data['balance'] !== null && $data['balance'] !== ''
            ? round((float) $data['balance'], 2)
            : max(0, round((float) $plan->price - $monthlyFee, 2));
        $changeLabel = ucfirst($data['change_type']);
        $notes = trim(implode("\n", array_filter([
            'Package setup · '.$changeLabel,
            $data['remarks'] ?? null,
            ! empty($data['cheque_no']) ? 'Cheque / Ref: '.$data['cheque_no'] : null,
        ])));

        DB::transaction(function () use (
            $member,
            $plan,
            $start,
            $end,
            $dueDay,
            $monthlyFee,
            $recordPayment,
            $paymentAmount,
            $balance,
            $notes,
            $data,
        ) {
            $member->update(['fee_due_day' => $dueDay]);

            if ($recordPayment && $paymentAmount >= 0.01) {
                $this->membershipPayments->recordPackageFee(
                    $member,
                    $plan,
                    $paymentAmount,
                    $start,
                    $end,
                    'active',
                    max(0, round((float) $plan->price - $paymentAmount - $balance, 2)),
                    null,
                    $data['account_id'] ?? null,
                    'cash',
                    $start,
                    $data['cheque_no'] ?? null,
                    $notes,
                    auth()->id(),
                    false,
                    $balance,
                );
            } else {
                $this->membershipPayments->syncSubscription(
                    $member,
                    $plan,
                    $monthlyFee,
                    $start,
                    $end,
                    'active',
                    $balance,
                );
            }
        });

        return redirect()
            ->route('members.package-setup', ['member_id' => $member->id])
            ->with('success', 'Package updated for '.$member->full_name.'.');
    }

    private function activePackages()
    {
        $catalogOrder = collect(config('membership_packages', []))
            ->map(fn ($row) => Str::slug($row['name']))
            ->values()
            ->all();

        return MembershipPlan::where('is_active', true)
            ->orderBy('price')
            ->orderBy('name')
            ->get()
            ->sortBy(function (MembershipPlan $plan) use ($catalogOrder) {
                $idx = array_search($plan->slug, $catalogOrder, true);

                return $idx === false ? 1000 + (float) $plan->price : $idx;
            })
            ->values();
    }
}
