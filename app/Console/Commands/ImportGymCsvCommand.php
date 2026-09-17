<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\ClassEnrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Member;
use App\Models\MemberSubscription;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Trainer;
use App\Models\ZktecoPunch;
use App\Services\MembershipPaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportGymCsvCommand extends Command
{
    protected $signature = 'gym:import-csv {path?} {--fresh : Wipe existing members and related finance/attendance first}';

    protected $description = 'Import Fit Generation gym members from old software CSV (m_id = biometric ID)';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('database/data/fit-gen-software-data.csv');
        if (! is_file($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->wipeMemberData();
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
        $feesSynced = 0;

        /** @var MembershipPaymentService $feeService */
        $feeService = app(MembershipPaymentService::class);

        DB::transaction(function () use ($handle, $header, $feeService, &$created, &$updated, &$skipped, &$feesSynced) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 5) {
                    $skipped++;
                    continue;
                }

                $data = [];
                foreach ($header as $i => $key) {
                    $data[$key] = $row[$i] ?? null;
                }

                $mId = trim((string) ($data['m_id'] ?? ''));
                $fullName = trim((string) ($data['full_name'] ?? ''));
                if ($mId === '' || $fullName === '') {
                    $skipped++;
                    continue;
                }

                [$first, $last] = $this->splitName($fullName);
                $joined = $this->parseDate($data['reg_date'] ?? null);
                $expires = $this->parseDate($data['pkg_expire'] ?? null);
                $monthly = $this->money($data['monthly_fee'] ?? $data['pkg_fee'] ?? 0);
                $pkgFee = $this->money($data['pkg_fee'] ?? 0);
                $paid = $this->money($data['t_payment'] ?? $monthly);
                $status = $this->mapStatus($data['status'] ?? 'Active');
                $gender = $this->mapGender($data['gender'] ?? null);
                $phone = $this->normalizePhone($data['m_contact'] ?? null);
                $trainerId = $this->resolveTrainerId($data['trainer_id'] ?? null);

                $notes = collect([
                    ($data['day_time'] ?? null) ? 'Shift: ' . trim((string) $data['day_time']) : null,
                    ($data['time_slot'] ?? null) && strtolower(trim((string) $data['time_slot'])) !== 'none'
                        ? 'Slot: ' . trim((string) $data['time_slot'])
                        : null,
                    ($data['relative'] ?? null) ? 'Relative: ' . trim((string) $data['relative']) : null,
                    ($data['add_fee'] ?? null) && (float) $data['add_fee'] > 0
                        ? 'Add fee: ' . $data['add_fee']
                        : null,
                    $pkgFee > 0 ? 'Pkg fee: ' . rtrim(rtrim(number_format($pkgFee, 2, '.', ''), '0'), '.') : null,
                    ($data['pkd_id'] ?? null) !== null && trim((string) $data['pkd_id']) !== ''
                        ? 'Pkg id: ' . trim((string) $data['pkd_id'])
                        : null,
                ])->filter()->implode(' | ');

                // Prefer catalog package by monthly fee (standard duration), keep CSV expiry on the fee period
                $plan = null;
                if ($monthly > 0) {
                    $plan = $this->planFor($monthly, 30);
                }

                $payload = [
                    'member_code' => 'MEM-' . str_pad($mId, 5, '0', STR_PAD_LEFT),
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

                if ($plan && $joined && $expires && $paid >= 0) {
                    $feeService->recordPackageFee(
                        $member,
                        $plan,
                        $paid,
                        $joined->toDateString(),
                        $expires->toDateString(),
                        'active',
                        null,
                        'CSV-FEE-' . (int) $mId,
                        null,
                        'cash',
                        $joined->toDateString(),
                        'CSV-FEE-' . (int) $mId,
                        'Imported package fee',
                        null,
                        true,
                        null,
                    );
                    $feesSynced++;
                } elseif ($plan && $joined && $expires) {
                    // Fallback: subscription only
                    $sub = $member->subscriptions()->where('status', 'active')->latest('id')->first();
                    $subPayload = [
                        'membership_plan_id' => $plan->id,
                        'start_date' => $joined->toDateString(),
                        'end_date' => $expires->toDateString(),
                        'amount_paid' => $paid,
                        'status' => 'active',
                        'notes' => 'Imported from old gym software',
                    ];
                    if ($sub) {
                        $sub->update($subPayload);
                    } else {
                        $member->subscriptions()->create($subPayload);
                    }
                }
            }
        });

        fclose($handle);

        $this->info("Import done. created={$created} updated={$updated} skipped={$skipped} fees_synced={$feesSynced}");
        $this->info('Total members: ' . Member::count());

        return self::SUCCESS;
    }

    private function wipeMemberData(): void
    {
        $this->warn('Wiping demo/existing member-related data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ZktecoPunch::query()->update(['member_id' => null]);
        Payment::query()->forceDelete();
        InvoiceItem::query()->delete();
        Invoice::query()->forceDelete();
        Attendance::query()->delete();
        ClassEnrollment::query()->delete();
        MemberSubscription::query()->delete();
        Member::withTrashed()->forceDelete();
        MembershipPlan::withTrashed()->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Wipe complete.');
    }

    private function resolveTrainerId(mixed $csvTrainerId): ?int
    {
        $raw = trim((string) $csvTrainerId);
        if ($raw === '' || $raw === '0') {
            return null;
        }

        $externalId = (int) $raw;
        if ($externalId <= 0) {
            return null;
        }

        $code = 'TRN-' . str_pad((string) $externalId, 3, '0', STR_PAD_LEFT);

        $trainer = Trainer::withTrashed()->where('trainer_code', $code)->first();
        if ($trainer) {
            if ($trainer->trashed()) {
                $trainer->restore();
            }
            if ($trainer->status !== 'active') {
                $trainer->update(['status' => 'active']);
            }

            return $trainer->id;
        }

        $trainer = Trainer::create([
            'trainer_code' => $code,
            'first_name' => 'Trainer',
            'last_name' => (string) $externalId,
            'status' => 'active',
            'hire_date' => now()->toDateString(),
        ]);

        return $trainer->id;
    }

    private function planFor(float $price, int $durationDays = 30): MembershipPlan
    {
        $this->ensureCatalogPlans();

        $catalogSlugs = collect(config('membership_packages', []))
            ->map(fn ($r) => Str::slug($r['name']))
            ->all();

        $catalogPlans = MembershipPlan::withTrashed()
            ->whereIn('slug', $catalogSlugs)
            ->get()
            ->each(function (MembershipPlan $plan) {
                if ($plan->trashed()) {
                    $plan->restore();
                }
                if (! $plan->is_active) {
                    $plan->update(['is_active' => true]);
                }
            });

        $price = round($price, 2);

        // Exact price match (prefer matching duration when several share a price)
        $exact = $catalogPlans
            ->filter(fn (MembershipPlan $p) => round((float) $p->price, 2) === $price)
            ->sortBy(fn (MembershipPlan $p) => abs(((int) $p->duration_days) - $durationDays))
            ->first();

        if ($exact) {
            return $exact;
        }

        // Otherwise nearest existing catalog package (never create Custom plans)
        $matched = app(MembershipPaymentService::class)->matchPackageForAmount($price, $catalogPlans);
        if ($matched) {
            return $matched;
        }

        // Absolute fallback: first catalog row
        $first = collect(config('membership_packages', []))->first();
        $slug = Str::slug($first['name'] ?? 'gym-access');

        return MembershipPlan::withTrashed()->where('slug', $slug)->firstOrFail();
    }

    private function ensureCatalogPlans(): void
    {
        foreach (config('membership_packages', []) as $row) {
            $slug = Str::slug($row['name']);
            MembershipPlan::withTrashed()->updateOrCreate(
                ['slug' => $slug],
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
            'cancelled', 'canceled', 'inactive', 'deactive', 'deactivated' => 'cancelled',
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
