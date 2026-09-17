<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Trainer;
use App\Services\MembershipPaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLatestDataCsvCommand extends Command
{
    protected $signature = 'gym:import-latest-data {path?}';

    protected $description = 'Full sync members from latest-data.csv (Member ID = biometric ID)';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('database/data/latest-data.csv');
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
        $noPlan = 0;

        DB::transaction(function () use (
            $handle,
            $header,
            $feeService,
            $catalogPlans,
            $cashId,
            &$created,
            &$updated,
            &$fees,
            &$skipped,
            &$noPlan
        ) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) {
                    $skipped++;
                    continue;
                }

                $data = [];
                foreach ($header as $i => $key) {
                    $data[$key] = $row[$i] ?? null;
                }

                $mId = preg_replace('/\D+/', '', (string) ($data['member id'] ?? $data['m_id'] ?? ''));
                $fullName = trim((string) ($data['name'] ?? $data['full_name'] ?? ''));
                if ($mId === '' || $fullName === '') {
                    $skipped++;
                    continue;
                }

                [$first, $last] = $this->splitName($fullName);
                $joined = $this->parseDate($data['reg date'] ?? $data['reg_date'] ?? null);
                $expires = $this->parseDate($data['next expiry'] ?? $data['pkg_expire'] ?? null);
                $packageFee = $this->money($data['package fee'] ?? $data['pkg_fee'] ?? 0);
                $paid = $this->money($data['paid amount'] ?? $data['t_payment'] ?? $packageFee);
                $due = $this->money($data['due amount'] ?? 0);
                $csvDiscount = $this->money($data['discount'] ?? 0);
                $admission = $this->money($data['admission fee'] ?? 0);
                $status = $this->mapStatus($data['status'] ?? 'Active');
                $gender = $this->mapGender($data['gender'] ?? null);
                $phone = $this->normalizePhone($data['contact'] ?? $data['m_contact'] ?? null);
                $packageLabel = trim((string) ($data['package'] ?? ''));
                $trainerId = $this->resolveTrainerId($data['trainer'] ?? null);

                $plan = $this->resolvePlan($packageLabel, $packageFee > 0 ? $packageFee : $paid, $catalogPlans, $feeService);
                if (! $plan) {
                    $this->warn("No catalog package for member={$mId} package={$packageLabel} fee={$packageFee}");
                    $noPlan++;
                }

                $notes = collect([
                    $packageLabel !== '' ? 'Source package: '.$packageLabel : null,
                    $packageFee > 0 ? 'Pkg fee: '.rtrim(rtrim(number_format($packageFee, 2, '.', ''), '0'), '.') : null,
                    $admission > 0 ? 'Admission fee: '.rtrim(rtrim(number_format($admission, 2, '.', ''), '0'), '.') : null,
                    $csvDiscount > 0 ? 'CSV discount: '.rtrim(rtrim(number_format($csvDiscount, 2, '.', ''), '0'), '.') : null,
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
                    'trainer_id' => $trainerId,
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

                if (! $plan || ! $joined || ! $expires) {
                    continue;
                }

                $amount = max($paid > 0 ? $paid : (float) $plan->price, 0.01);
                $planPrice = (float) $plan->price;
                $discount = $csvDiscount > 0
                    ? $csvDiscount
                    : max(0, round($planPrice - $amount, 2));
                $balance = max(0, round($due, 2));

                $feeEnd = $expires->toDateString();
                $feeStart = $expires->copy()->subDays(max(1, (int) $plan->duration_days))->toDateString();
                if ($joined->gt(Carbon::parse($feeStart))) {
                    $feeStart = $joined->toDateString();
                }

                $reference = 'CSV-LATEST-'.$mId;
                $existing = Payment::where('reference', $reference)->first();
                $paymentPayload = [
                    'member_id' => $member->id,
                    'membership_plan_id' => $plan->id,
                    'account_id' => $cashId,
                    'amount' => $amount,
                    'discount' => $discount,
                    'balance' => $balance,
                    'method' => 'cash',
                    'payment_date' => $feeStart,
                    'fee_start_date' => $feeStart,
                    'fee_end_date' => $feeEnd,
                    'reference' => $reference,
                    'status' => 'completed',
                    'notes' => 'Synced from latest-data CSV'.($packageLabel ? ' · '.$packageLabel : ''),
                    'invoice_id' => null,
                ];

                if ($existing) {
                    $delta = $amount - (float) $existing->amount;
                    $existing->update($paymentPayload);
                    if ($cashId && abs($delta) > 0.00001) {
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

                $subStatus = $status === 'cancelled' ? 'cancelled' : 'active';

                $feeService->syncSubscription(
                    $member,
                    $plan,
                    $amount,
                    $feeStart,
                    $feeEnd,
                    $subStatus,
                    $balance
                );
                $fees++;
            }
        });

        fclose($handle);

        $this->info("Import done. created={$created} updated={$updated} fees={$fees} skipped={$skipped} no_plan={$noPlan}");
        $this->info('Total members: '.Member::count());

        return self::SUCCESS;
    }

    private function resolveTrainerId(mixed $raw): ?int
    {
        $name = trim((string) $raw);
        if ($name === '' || strcasecmp($name, 'NONE') === 0) {
            return null;
        }

        // Broken encoding / replacement char from export
        if (preg_match('/\x{FFFD}/u', $name) || ! preg_match('/[A-Za-z]/', $name)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', ' ', $name));
        $aliases = [
            'DANISH MANSOOR' => ['Danish', 'Mansoor'],
            'DANISH' => ['Danish', 'Mansoor'],
            'ASMA' => ['Asma', ''],
            'JIBRAN' => ['Jibran', ''],
            'SUMBUL' => ['Sumbul', ''],
            'KOMAL' => ['Komal', ''],
            'ALTAMASH' => ['Altamash', ''],
        ];

        [$first, $last] = $aliases[$normalized] ?? $this->splitName($name);
        $first = trim($first);
        $last = trim($last);

        $trainer = Trainer::withTrashed()
            ->get()
            ->first(function (Trainer $t) use ($first, $last, $normalized) {
                $full = strtoupper(trim($t->first_name.' '.$t->last_name));
                if ($full === $normalized) {
                    return true;
                }
                if (strcasecmp($t->first_name, $first) === 0) {
                    // Prefer Danish/Asma legacy FitGen rows
                    return true;
                }

                return false;
            });

        if ($trainer) {
            if ($trainer->trashed()) {
                $trainer->restore();
            }
            $trainer->update([
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : $trainer->last_name,
                'status' => 'active',
                'deleted_at' => null,
            ]);

            return $trainer->id;
        }

        $code = 'TRN-'.strtoupper(Str::slug($first.($last !== '' ? '-'.$last : ''), ''));
        $code = substr($code, 0, 20);
        if (Trainer::withTrashed()->where('trainer_code', $code)->exists()) {
            $code = 'TRN-'.str_pad((string) (Trainer::withTrashed()->max('id') + 1), 3, '0', STR_PAD_LEFT);
        }

        $trainer = Trainer::create([
            'trainer_code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'status' => 'active',
            'hire_date' => now()->toDateString(),
        ]);

        return $trainer->id;
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

        if ($fee > 0) {
            return $feeService->matchPackageForAmount($fee, $catalogPlans);
        }

        return null;
    }

    private function packageAlias(string $label): ?string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $label)));
        $normalized = str_replace('PKG', 'PACKAGE', $normalized);

        $map = [
            'BUDDY PACKAGE' => 'BUDDY PACKAGE',
            'GYM ACCESS' => 'GYM ACCESS',
            'GYM ACCESS + CARDIO' => 'CARDIO + GYM ACCESS',
            'CARDIO + GYM ACCESS' => 'CARDIO + GYM ACCESS',
            'CARDIO' => 'CARDIO',
            'ONLY CARDIO' => 'CARDIO',
            'PLATINUM' => 'PLATINUM',
            'PLATINUM + CARDIO' => 'PLATINUM + CARDIO',
            'GOLD' => 'GOLD',
            'GOLD + CARDIO' => 'GOLD + CARDIO',
            'EXECUTIVE' => 'EXECUTIVE',
            'EXECUTIVE + CARDIO' => 'EXECUTIVE + CARDIO',
            'BASIC' => 'CARDIO + GYM ACCESS',
            'BASIC + CARDIO' => 'PACKAGE 2',
            'BOOTCAMP' => 'PACKAGE 1',
            'BOOTCAMP + CARDIO' => 'PACKAGE 2',
            'KIDS BOOTCAMP' => 'PACKAGE 1',
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
        ];

        return $map[$normalized] ?? null;
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
