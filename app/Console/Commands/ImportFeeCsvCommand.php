<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\MembershipPaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportFeeCsvCommand extends Command
{
    protected $signature = 'gym:import-fees {path?} {--replace-member-fees : Remove CSV-FEE-* member-import payments before importing}';

    protected $description = 'Import fee history from old software tbl_fee2 CSV (mem_id = biometric / device_user_id)';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('database/data/tbl_fee2.csv');
        if (! is_file($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        $this->ensureCatalogPlans();

        if ($this->option('replace-member-fees')) {
            $this->removeMemberImportFees();
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            $this->error('Empty CSV.');
            fclose($handle);

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $missingMember = 0;
        $latestByMember = []; // member_id => row payload for subscription sync

        /** @var MembershipPaymentService $feeService */
        $feeService = app(MembershipPaymentService::class);
        $catalogPlans = $this->catalogPlans();
        $cashAccountId = $feeService->defaultCashAccountId();

        DB::transaction(function () use (
            $handle,
            $header,
            $feeService,
            $catalogPlans,
            $cashAccountId,
            &$created,
            &$updated,
            &$skipped,
            &$missingMember,
            &$latestByMember
        ) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 5) {
                    $skipped++;
                    continue;
                }

                $data = [];
                foreach ($header as $i => $key) {
                    $data[$key] = $row[$i] ?? null;
                }

                $fId = trim((string) ($data['f_id'] ?? ''));
                $memId = trim((string) ($data['mem_id'] ?? ''));
                if ($fId === '' || $memId === '') {
                    $skipped++;
                    continue;
                }

                $member = Member::withTrashed()->where('device_user_id', (string) (int) $memId)->first();
                if (! $member) {
                    $missingMember++;
                    continue;
                }
                if ($member->trashed()) {
                    $member->restore();
                }

                $payDate = $this->parseDate($data['pay_date'] ?? null);
                $expDate = $this->parseDate($data['exp_date'] ?? null);
                $feeMonth = $this->money($data['fee_month'] ?? 0);
                $feePaid = $this->money($data['fee_paid'] ?? $feeMonth);
                $status = $this->mapFeeStatus($data['fee_status'] ?? 'Paid');

                if (! $payDate || ! $expDate) {
                    $skipped++;
                    continue;
                }

                $matchAmount = $feeMonth > 0 ? $feeMonth : $feePaid;
                $plan = $feeService->matchPackageForAmount($matchAmount, $catalogPlans);
                if (! $plan) {
                    $skipped++;
                    continue;
                }

                $feeStart = $payDate->toDateString();
                $feeEnd = $expDate->toDateString();
                if ($payDate->gt($expDate)) {
                    $feeStart = $expDate->copy()->subDays(max(1, (int) $plan->duration_days))->toDateString();
                }

                $reference = 'CSV-FEE2-'.$fId;
                $discount = max(0, round((float) $plan->price - $feePaid, 2));
                $existing = Payment::where('reference', $reference)->first();
                $payload = [
                    'member_id' => $member->id,
                    'membership_plan_id' => $plan->id,
                    'account_id' => $cashAccountId,
                    'amount' => max($feePaid, 0.01),
                    'discount' => $discount,
                    'balance' => 0,
                    'method' => 'cash',
                    'payment_date' => $payDate->toDateString(),
                    'fee_start_date' => $feeStart,
                    'fee_end_date' => $feeEnd,
                    'reference' => $reference,
                    'status' => $status,
                    'notes' => 'Imported fee record #'.$fId.($feeMonth > 0 ? ' · Fee month '.$feeMonth : ''),
                    'invoice_id' => null,
                ];

                if ($existing) {
                    $oldAmount = (float) $existing->amount;
                    $oldStatus = $existing->status;
                    $existing->update($payload);
                    // Adjust cash only for completed amount changes
                    if ($cashAccountId && $oldStatus === 'completed' && $status === 'completed') {
                        $delta = (float) $payload['amount'] - $oldAmount;
                        if (abs($delta) > 0.00001) {
                            $this->adjustCash($cashAccountId, $delta);
                        }
                    } elseif ($cashAccountId && $oldStatus !== 'completed' && $status === 'completed') {
                        $this->adjustCash($cashAccountId, (float) $payload['amount']);
                    } elseif ($cashAccountId && $oldStatus === 'completed' && $status !== 'completed') {
                        $this->adjustCash($cashAccountId, -1 * $oldAmount);
                    }
                    $updated++;
                    $payment = $existing;
                } else {
                    $payload['payment_number'] = $this->generatePaymentNumber();
                    $payment = Payment::create($payload);
                    if ($cashAccountId && $status === 'completed') {
                        $this->adjustCash($cashAccountId, (float) $payment->amount);
                    }
                    $created++;
                }

                $key = $member->id;
                $current = $latestByMember[$key] ?? null;
                if (
                    ! $current
                    || $expDate->gt(Carbon::parse($current['fee_end']))
                    || ($expDate->eq(Carbon::parse($current['fee_end'])) && $payDate->gte(Carbon::parse($current['pay_date'])))
                ) {
                    $latestByMember[$key] = [
                        'member' => $member,
                        'plan' => $plan,
                        'amount' => $feePaid,
                        'fee_start' => $feeStart,
                        'fee_end' => $feeEnd,
                        'pay_date' => $payDate->toDateString(),
                    ];
                }
            }

            foreach ($latestByMember as $row) {
                $feeService->syncSubscription(
                    $row['member'],
                    $row['plan'],
                    $row['amount'],
                    $row['fee_start'],
                    $row['fee_end'],
                    'active',
                    0
                );
            }
        });

        fclose($handle);

        $this->info("Fee import done. created={$created} updated={$updated} skipped={$skipped} missing_member={$missingMember}");
        $this->info('Subscriptions synced for '.count($latestByMember).' members.');
        $this->info('Total payments: '.Payment::count());

        return self::SUCCESS;
    }

    private function removeMemberImportFees(): void
    {
        $legacy = Payment::query()
            ->where('reference', 'like', 'CSV-FEE-%')
            ->where('reference', 'not like', 'CSV-FEE2-%')
            ->get();

        if ($legacy->isEmpty()) {
            $this->info('No CSV-FEE-* member-import payments to replace.');

            return;
        }

        $this->warn('Removing '.$legacy->count().' CSV-FEE-* payments before fee history import...');

        DB::transaction(function () use ($legacy) {
            foreach ($legacy as $payment) {
                if ($payment->status === 'completed' && $payment->account_id) {
                    $this->adjustCash((int) $payment->account_id, -1 * (float) $payment->amount);
                }
                $payment->delete();
            }
        });
    }

    private function ensureCatalogPlans(): void
    {
        foreach (config('membership_packages', []) as $row) {
            MembershipPlan::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                [
                    'name' => $row['name'],
                    'tier' => $row['tier'] ?? 'basic',
                    'description' => $row['name'].' membership package.',
                    'price' => $row['price'],
                    'duration_days' => $row['duration_days'] ?? 30,
                    'features' => null,
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function catalogPlans()
    {
        $slugs = collect(config('membership_packages', []))
            ->map(fn ($r) => Str::slug($r['name']))
            ->all();

        return MembershipPlan::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->get();
    }

    private function adjustCash(int $accountId, float $delta): void
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
            $number = 'PAY-'.now()->format('Y').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Payment::where('payment_number', $number)->exists());

        return $number;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'none') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) preg_replace('/[^0-9.]/', '', (string) $value);
    }

    private function mapFeeStatus(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        return match ($s) {
            'paid', 'complete', 'completed' => 'completed',
            'pending', 'unpaid', 'due' => 'pending',
            'failed', 'cancelled', 'canceled' => 'failed',
            default => 'completed',
        };
    }
}
