<?php

namespace App\Console\Commands;

use App\Models\MemberSubscription;
use App\Models\MembershipPlan;
use App\Services\MembershipPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignPackagesAndSyncRevenueCommand extends Command
{
    protected $signature = 'gym:assign-packages-revenue
        {--force : Skip confirmation}
        {--assign-only : Only assign packages, do not create payments}
        {--revenue-only : Only create payments for subscriptions that already have packages}';

    protected $description = 'Assign catalog packages to members (with discount on underpay) and sync fee amounts into payments/revenue';

    public function handle(MembershipPaymentService $fees): int
    {
        if (! $this->option('force') && ! $this->confirm('Assign packages and sync fee revenue from member subscriptions?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $plans = MembershipPlan::where('is_active', true)->get();
        if ($plans->isEmpty()) {
            $this->error('No active packages found. Run gym:replace-packages first.');

            return self::FAILURE;
        }

        $assigned = 0;
        $revenueCreated = 0;
        $revenueSkipped = 0;
        $discountTotal = 0.0;

        DB::transaction(function () use ($fees, $plans, &$assigned, &$revenueCreated, &$revenueSkipped, &$discountTotal) {
            $assignOnly = $this->option('assign-only');
            $revenueOnly = $this->option('revenue-only');

            MemberSubscription::query()
                ->with(['member', 'plan'])
                ->orderBy('id')
                ->chunkById(100, function ($subs) use ($fees, $plans, $assignOnly, $revenueOnly, &$assigned, &$revenueCreated, &$revenueSkipped, &$discountTotal) {
                    foreach ($subs as $sub) {
                        if (! $sub->member) {
                            continue;
                        }

                        $plan = $sub->plan;
                        if (! $revenueOnly && ! $plan) {
                            $result = $fees->assignPackageToSubscription($sub, $fees->matchPackageForAmount((float) $sub->amount_paid, $plans));
                            if ($result['ok']) {
                                $assigned++;
                                $discountTotal += (float) $result['discount'];
                                $plan = $result['plan'];
                                $sub->setRelation('plan', $plan);
                            }
                        }

                        if ($assignOnly || ! $plan) {
                            continue;
                        }

                        // Ensure plan is set even if revenue-only and somehow null
                        if (! $sub->membership_plan_id) {
                            continue;
                        }

                        $amount = (float) $sub->amount_paid;
                        if ($amount <= 0) {
                            $revenueSkipped++;
                            continue;
                        }

                        $key = 'FEE-SUB-' . $sub->id;
                        $recorded = $fees->recordPackageFee(
                            $sub->member,
                            $plan,
                            $amount,
                            $sub->start_date?->format('Y-m-d'),
                            $sub->end_date?->format('Y-m-d'),
                            $sub->status ?: 'active',
                            null,
                            $key
                        );

                        if (! empty($recorded['skipped'])) {
                            $revenueSkipped++;
                        } else {
                            $revenueCreated++;
                            $discountTotal += (float) ($recorded['discount'] ?? 0);
                        }
                    }
                });
        });

        $this->info("Packages assigned: {$assigned}");
        $this->info("Revenue payments created: {$revenueCreated}");
        $this->info("Revenue skipped (already synced / zero): {$revenueSkipped}");
        $this->info('Discount total applied: ' . number_format($discountTotal, 2));

        $paySum = \App\Models\Payment::where('status', 'completed')->sum('amount');
        $this->info('Completed payments (revenue): ' . number_format((float) $paySum, 2));

        return self::SUCCESS;
    }
}
