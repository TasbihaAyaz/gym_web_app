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

class ImportAvailableMembersCsvCommand extends Command
{
    protected $signature = 'gym:import-available-members {path?}';

    protected $description = 'Import Fit Generation “available members” CSV (package by name, #m_id, Joined date text)';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('database/data/fit_generation_members_available.csv');
        if (! is_file($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        $this->ensureCatalogPlans();

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            $this->error('Empty CSV.');
            fclose($handle);

            return self::FAILURE;
        }

        $header = array_map(function ($h) {
            $h = (string) $h;
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
            $h = preg_replace('/^\x{FEFF}/u', '', $h) ?? $h;

            return strtolower(trim($h));
        }, $header);
        $feeService = app(MembershipPaymentService::class);
        $catalogPlans = $this->catalogPlans();
        $cashId = $feeService->defaultCashAccountId();

        $created = 0;
        $updated = 0;
        $fees = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $handle,
            $header,
            $feeService,
            $catalogPlans,
            $cashId,
            &$created,
            &$updated,
            &$fees,
            &$skipped
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

                $mId = preg_replace('/\D+/', '', (string) ($data['m_id'] ?? ''));
                $fullName = trim((string) ($data['full_name'] ?? ''));
                if ($mId === '' || $fullName === '') {
                    $skipped++;
                    continue;
                }

                [$first, $last] = $this->splitName($fullName);
                $joined = $this->parseJoinedDate($data['reg_date'] ?? null);
                $expires = $this->parseDate($data['pkg_expire'] ?? null);
                $fee = $this->money($data['monthly_fee'] ?? 0);
                $status = $this->mapStatus($data['status'] ?? 'Active');
                $gender = $this->mapGender($data['gender'] ?? null);
                $phone = $this->normalizePhone($data['m_contact'] ?? null);
                $packageLabel = trim((string) ($data['package'] ?? ''));

                $plan = $this->resolvePlan($packageLabel, $fee, $catalogPlans, $feeService);
                if (! $plan) {
                    $this->warn("No catalog package for m_id={$mId} package={$packageLabel} fee={$fee}");
                    $skipped++;
                    continue;
                }

                $notes = collect([
                    $packageLabel !== '' ? 'Source package: '.$packageLabel : null,
                    $fee > 0 ? 'Listed fee: '.rtrim(rtrim(number_format($fee, 2, '.', ''), '0'), '.') : null,
                ])->filter()->implode(' | ');

                $payload = [
                    'member_code' => 'MEM-'.str_pad($mId, 5, '0', STR_PAD_LEFT),
                    'device_user_id' => (string) (int) $mId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => $phone,
                    'gender' => $gender,
                    'status' => $status,
                    'joined_at' => $joined?->toDateString(),
                    'notes' => $notes !== '' ? $notes : null,
                    'deleted_at' => null,
                ];

                $member = Member::withTrashed()->where('device_user_id', (string) (int) $mId)->first();
                if ($member) {
                    if ($member->trashed()) {
                        $member->restore();
                    }
                    $member->update($payload);
                    $updated++;
                } else {
                    $member = Member::create($payload);
                    $created++;
                }

                if ($joined && $expires) {
                    $paid = $fee > 0 ? $fee : (float) $plan->price;
                    $reference = 'CSV-AVAIL-'.$mId;
                    $existing = Payment::where('reference', $reference)->first();
                    $amount = max($paid, 0.01);
                    $discount = max(0, round((float) $plan->price - $amount, 2));

                    $paymentPayload = [
                        'member_id' => $member->id,
                        'membership_plan_id' => $plan->id,
                        'account_id' => $cashId,
                        'amount' => $amount,
                        'discount' => $discount,
                        'balance' => 0,
                        'method' => 'cash',
                        'payment_date' => $joined->toDateString(),
                        'fee_start_date' => $joined->toDateString(),
                        'fee_end_date' => $expires->toDateString(),
                        'reference' => $reference,
                        'status' => 'completed',
                        'notes' => 'Imported from available members CSV'.($packageLabel ? ' · '.$packageLabel : ''),
                        'invoice_id' => null,
                    ];

                    if ($existing) {
                        $delta = $amount - (float) $existing->amount;
                        $existing->update($paymentPayload);
                        if ($cashId && abs($delta) > 0.00001 && $existing->status === 'completed') {
                            $this->adjustCash($cashId, $delta);
                        }
                    } else {
                        do {
                            $number = 'PAY-'.now()->format('Y').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                        } while (Payment::where('payment_number', $number)->exists());

                        $paymentPayload['payment_number'] = $number;
                        Payment::create($paymentPayload);
                        if ($cashId) {
                            $this->adjustCash($cashId, $amount);
                        }
                    }

                    $feeService->syncSubscription(
                        $member,
                        $plan,
                        $amount,
                        $joined->toDateString(),
                        $expires->toDateString(),
                        'active',
                        0
                    );
                    $fees++;
                }
            }
        });

        fclose($handle);

        $this->info("Import done. created={$created} updated={$updated} fees={$fees} skipped={$skipped}");
        $this->info('Total members: '.Member::count());

        return self::SUCCESS;
    }

    private function resolvePlan(
        string $packageLabel,
        float $fee,
        $catalogPlans,
        MembershipPaymentService $feeService
    ): ?MembershipPlan {
        $alias = $this->packageAlias($packageLabel);
        if ($alias) {
            $slug = Str::slug($alias);
            $byName = $catalogPlans->first(fn (MembershipPlan $p) => $p->slug === $slug);
            if ($byName) {
                return $byName;
            }
        }

        // Fallback: nearest catalog price from listed fee
        if ($fee > 0) {
            return $feeService->matchPackageForAmount($fee, $catalogPlans);
        }

        return null;
    }

    private function packageAlias(string $label): ?string
    {
        $key = strtoupper(trim(preg_replace('/\s+/', ' ', $label)));
        $key = str_replace(['PKG', 'PACKAGE'], ['PACKAGE', 'PACKAGE'], $key);

        $map = [
            'BUDDY PKG' => 'BUDDY PACKAGE',
            'BUDDY PACKAGE' => 'BUDDY PACKAGE',
            'GYM ACCESS' => 'GYM ACCESS',
            'GYM ACCESS + CARDIO' => 'CARDIO + GYM ACCESS',
            'CARDIO + GYM ACCESS' => 'CARDIO + GYM ACCESS',
            'CARDIO' => 'CARDIO',
            'PLATINUM' => 'PLATINUM',
            'PLATINUM + CARDIO' => 'PLATINUM + CARDIO',
            'GOLD' => 'GOLD',
            'GOLD + CARDIO' => 'GOLD + CARDIO',
            'EXECUTIVE' => 'EXECUTIVE',
            'EXECUTIVE + CARDIO' => 'EXECUTIVE + CARDIO',
            'PACKAGE 01' => 'PACKAGE 1',
            'PACKAGE 1' => 'PACKAGE 1',
            'PACKAGE 02' => 'PACKAGE 2',
            'PACKAGE 2' => 'PACKAGE 2',
            'PACKAGE 03' => 'PACKAGE 3',
            'PACKAGE 3' => 'PACKAGE 3',
            'PACKAGE 04' => 'PACKAGE 4',
            'PACKAGE 4' => 'PACKAGE 4',
            'PACKAGE 05' => 'PACKAGE 5',
            'PACKAGE 5' => 'PACKAGE 5',
            'PACKAGE 06' => 'PACKAGE 6',
            'PACKAGE 6' => 'PACKAGE 6',
            'ALL IN ONE' => 'ALL IN ONE',
            '3 MONTHS' => '3 MONTHS',
            '6 MONTHS' => '6 MONTHS',
            '12 MONTHS' => '12 MONTHS',
            // No BOOTCAMP in catalog — map to nearest named package by common gym usage
            'BOOTCAMP' => 'PACKAGE 1',
            'KIDS BOOTCAMP' => 'PACKAGE 1',
        ];

        // Normalize "BUDDY PKG" style after PACKAGE expand
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $label)));
        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        // Try replacing PKG with PACKAGE
        $alt = str_replace('PKG', 'PACKAGE', $normalized);
        if (isset($map[$alt])) {
            return $map[$alt];
        }

        return $map[$key] ?? null;
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

        return MembershipPlan::query()->whereIn('slug', $slugs)->where('is_active', true)->get();
    }

    private function adjustCash(int $accountId, float $delta): void
    {
        if ($delta == 0.0) {
            return;
        }
        $account = Account::find($accountId);
        if ($account) {
            $account->update(['current_balance' => (float) $account->current_balance + $delta]);
        }
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        $first = $parts[0] ?? 'Member';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        return [$first, $last];
    }

    private function parseJoinedDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/Joined\s+(\d{1,2}-[A-Za-z]{3}-\d{2,4})/i', $value, $m)) {
            return $this->parseDate($m[1]);
        }

        return $this->parseDate($value);
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

    private function mapStatus(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        return match ($s) {
            'active' => 'active',
            'leave', 'on leave' => 'leave',
            'pending' => 'pending',
            'cancelled', 'canceled', 'inactive', 'deactive' => 'cancelled',
            default => 'active',
        };
    }

    private function mapGender(?string $gender): ?string
    {
        $g = strtolower(trim((string) $gender));

        return match ($g) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other' => 'other',
            default => null,
        };
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '' || $phone === '0') {
            return null;
        }

        return $phone;
    }
}
