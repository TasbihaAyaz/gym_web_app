<?php

namespace App\Console\Commands;

use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReplaceMembershipPackagesCommand extends Command
{
    protected $signature = 'gym:replace-packages {--force : Skip confirmation}';

    protected $description = 'Remove old membership packages and install the Fit Generation catalog';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Replace ALL membership packages with the new catalog?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $catalog = config('membership_packages', []);
        if ($catalog === []) {
            $this->error('config/membership_packages.php is empty.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($catalog) {
            $keepIds = [];
            $bySlug = [];

            foreach ($catalog as $row) {
                $slug = Str::slug($row['name']);
                $plan = MembershipPlan::withTrashed()->firstOrNew(['slug' => $slug]);
                $plan->fill([
                    'name' => $row['name'],
                    'tier' => $row['tier'] ?? 'basic',
                    'description' => ($row['name'] ?? 'Package') . ' membership package.',
                    'price' => $row['price'],
                    'duration_days' => $row['duration_days'] ?? 30,
                    'features' => null,
                    'is_active' => true,
                    'deleted_at' => null,
                ]);
                $plan->save();
                $keepIds[] = $plan->id;
                $bySlug[$slug] = $plan;
            }

            // Remap subscriptions: unique price → that plan; ambiguous/missing → null (keep dates)
            $priceCounts = collect($catalog)->countBy(fn ($r) => (string) (float) $r['price']);
            $uniquePriceToPlan = [];
            foreach ($catalog as $row) {
                $key = (string) (float) $row['price'];
                if ((int) $priceCounts->get($key) === 1) {
                    $uniquePriceToPlan[$key] = $bySlug[Str::slug($row['name'])];
                }
            }

            $remapped = 0;
            $cleared = 0;
            MemberSubscription::query()->chunkById(200, function ($subs) use ($uniquePriceToPlan, &$remapped, &$cleared) {
                foreach ($subs as $sub) {
                    $oldPlan = MembershipPlan::withTrashed()->find($sub->membership_plan_id);
                    $priceKey = $oldPlan ? (string) (float) $oldPlan->price : null;
                    if ($priceKey !== null && isset($uniquePriceToPlan[$priceKey])) {
                        $sub->membership_plan_id = $uniquePriceToPlan[$priceKey]->id;
                        $sub->save();
                        $remapped++;
                    } else {
                        $sub->membership_plan_id = null;
                        $sub->save();
                        $cleared++;
                    }
                }
            });

            // Detach invoices from old plans (keep invoice history)
            DB::table('invoices')
                ->whereNotNull('membership_plan_id')
                ->whereNotIn('membership_plan_id', $keepIds)
                ->update(['membership_plan_id' => null]);

            // Hard-remove every plan not in the new catalog
            MembershipPlan::withTrashed()
                ->whereNotIn('id', $keepIds)
                ->forceDelete();

            $this->info('Installed ' . count($keepIds) . ' packages.');
            $this->info("Subscriptions remapped: {$remapped}, plan cleared (ambiguous price): {$cleared}.");
        });

        $this->table(
            ['Package', 'Price (PKR)', 'Days'],
            collect(config('membership_packages'))->map(fn ($r) => [
                $r['name'],
                number_format((float) $r['price'], 0),
                $r['duration_days'],
            ])->all()
        );

        return self::SUCCESS;
    }
}
