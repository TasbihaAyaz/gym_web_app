<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;

class CancelUnpaidMembersCommand extends Command
{
    protected $signature = 'members:cancel-unpaid
                            {--months=3 : Members older than this many months with no completed fee payment since then}
                            {--dry-run : Show who would be updated without saving}
                            {--force : Skip confirmation}';

    protected $description = 'Cancel members older than N months who have not paid any fee in the last N months; restore cancelled members who paid recently';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months)->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        // Older than N months AND no completed fee payment on/after cutoff
        $toCancel = Member::query()
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($cutoff) {
                $q->whereDate('joined_at', '<', $cutoff)
                    ->orWhere(function ($inner) use ($cutoff) {
                        $inner->whereNull('joined_at')
                            ->whereDate('created_at', '<', $cutoff);
                    });
            })
            ->whereDoesntHave('payments', function ($payments) use ($cutoff) {
                $payments
                    ->where('status', 'completed')
                    ->where('amount', '>', 0)
                    ->whereDate('payment_date', '>=', $cutoff);
            });

        // Wrongly cancelled earlier: paid a completed fee since cutoff
        $toRestore = Member::query()
            ->where('status', 'cancelled')
            ->whereHas('payments', function ($payments) use ($cutoff) {
                $payments
                    ->where('status', 'completed')
                    ->where('amount', '>', 0)
                    ->whereDate('payment_date', '>=', $cutoff);
            });

        $cancelCount = (clone $toCancel)->count();
        $restoreCount = (clone $toRestore)->count();

        $this->info("Cutoff: {$cutoff}");
        $this->info("Rule: joined before cutoff AND no completed payment (amount > 0) on/after cutoff");
        $this->info("Would cancel: {$cancelCount}");
        $this->info("Would restore to active: {$restoreCount}");

        if ($cancelCount === 0 && $restoreCount === 0) {
            $this->info('Nothing to update.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            if ($restoreCount > 0) {
                $this->line('');
                $this->warn('Sample restores (first 20):');
                $this->table(
                    ['Code', 'Name', 'Joined', 'Last payment'],
                    (clone $toRestore)
                        ->orderByDesc('joined_at')
                        ->limit(20)
                        ->get()
                        ->map(fn (Member $m) => [
                            $m->member_code,
                            $m->full_name,
                            optional($m->joined_at)?->toDateString() ?: optional($m->created_at)?->toDateString(),
                            $this->lastPaymentDate($m) ?: '—',
                        ])
                );
            }

            if ($cancelCount > 0) {
                $this->line('');
                $this->warn('Sample cancels (first 20):');
                $this->table(
                    ['Code', 'Name', 'Joined', 'Last payment'],
                    (clone $toCancel)
                        ->orderBy('joined_at')
                        ->limit(20)
                        ->get()
                        ->map(fn (Member $m) => [
                            $m->member_code,
                            $m->full_name,
                            optional($m->joined_at)?->toDateString() ?: optional($m->created_at)?->toDateString(),
                            $this->lastPaymentDate($m) ?: '—',
                        ])
                );
            }

            $this->warn('Dry run only — no members were updated.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Cancel {$cancelCount} and restore {$restoreCount} member(s)?", true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $cancelled = $toCancel->update(['status' => 'cancelled']);
        $restored = $toRestore->update(['status' => 'active']);

        $this->info("Cancelled: {$cancelled}");
        $this->info("Restored to active: {$restored}");

        return self::SUCCESS;
    }

    private function lastPaymentDate(Member $member): ?string
    {
        $date = $member->payments()
            ->where('status', 'completed')
            ->where('amount', '>', 0)
            ->max('payment_date');

        return $date ? (string) $date : null;
    }
}
