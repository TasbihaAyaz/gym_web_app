<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\Trainer;
use App\Models\ZktecoDevice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $range = $request->get('range', 'week');
        if (! in_array($range, ['week', 'month', 'year'], true)) {
            $range = 'week';
        }

        [$from, $to] = $this->rangeBounds($range);
        [$prevFrom, $prevTo] = $this->previousBounds($from, $to);

        $revenuePeriod = $request->get('revenue_period', $range === 'year' ? 'month' : 'week');
        if (! in_array($revenuePeriod, ['week', 'month'], true)) {
            $revenuePeriod = 'week';
        }

        $barPeriod = $request->get('bar_period', 'month');
        if (! in_array($barPeriod, ['month', 'year'], true)) {
            $barPeriod = 'month';
        }

        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();

        $monthlyRevenue = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $prevRevenue = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$prevFrom->toDateString(), $prevTo->toDateString()])
            ->sum('amount');

        $today = now()->toDateString();

        $pendingFeeMembers = Member::query()
            ->with(['activeSubscription.plan'])
            ->where('status', 'active')
            ->whereHas('activeSubscription', fn ($q) => $q->whereDate('end_date', '<', $today))
            ->orderBy(
                MemberSubscription::select('end_date')
                    ->whereColumn('member_subscriptions.member_id', 'members.id')
                    ->where('status', 'active')
                    ->latest('id')
                    ->limit(1)
            )
            ->take(4)
            ->get();

        $duePayments = (float) $pendingFeeMembers
            ->sum(fn (Member $m) => (float) ($m->activeSubscription?->plan?->price ?? $m->activeSubscription?->amount_paid ?? 0));

        $dueCount = Member::query()
            ->where('status', 'active')
            ->whereHas('activeSubscription', fn ($q) => $q->whereDate('end_date', '<', $today))
            ->count();

        $prevDue = (float) Member::query()
            ->with(['activeSubscription.plan'])
            ->where('status', 'active')
            ->whereHas('activeSubscription', function ($q) use ($from) {
                $q->whereDate('end_date', '<', $from->toDateString());
            })
            ->get()
            ->sum(fn (Member $m) => (float) ($m->activeSubscription?->plan?->price ?? $m->activeSubscription?->amount_paid ?? 0));

        $newEnrollments = Member::whereBetween('joined_at', [$from->toDateString(), $to->toDateString()])->count();
        $prevEnrollments = Member::whereBetween('joined_at', [$prevFrom->toDateString(), $prevTo->toDateString()])->count();

        $prevActive = Member::where('status', 'active')
            ->where('created_at', '<=', $prevTo)
            ->count();

        $membershipStatus = [
            'active' => Member::where('status', 'active')->count(),
            'leave' => Member::where('status', 'leave')->count(),
            'pending' => Member::where('status', 'pending')->count(),
            'cancelled' => Member::where('status', 'cancelled')->count(),
        ];

        $recentMembers = Member::query()
            ->with(['activeSubscription.plan'])
            ->latest('joined_at')
            ->latest('id')
            ->take(5)
            ->get();

        $popularClasses = GymClass::query()
            ->with('trainer')
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->take(4)
            ->get();

        $trainerLoad = Trainer::query()
            ->withCount(['members' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('members_count')
            ->orderBy('first_name')
            ->get();

        $selfTrainingCount = Member::whereNull('trainer_id')->where('status', 'active')->count();

        $trainerChart = $trainerLoad->take(10);

        $charts = [
            'currency' => currency_symbol(),
            'revenue' => $this->revenueSeries($revenuePeriod),
            'membership' => array_values($membershipStatus),
            'incomeExpenses' => $this->incomeExpenseSeries($barPeriod),
            'trainerPerformance' => [
                'labels' => $trainerChart->pluck('full_name')->values()->all(),
                'values' => $trainerChart->pluck('members_count')->map(fn ($n) => (int) $n)->values()->all(),
            ],
        ];

        $admin = auth()->user();
        if ($admin && ! $admin->relationLoaded('role')) {
            $admin->load('role');
        }

        $stats = [
            'range' => $range,
            'range_label' => $from->format('M j') . ' — ' . $to->format('M j, Y'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'revenue_period' => $revenuePeriod,
            'bar_period' => $barPeriod,
            'welcome_name' => $admin?->name ? explode(' ', $admin->name)[0] : 'there',
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'monthly_revenue' => $monthlyRevenue,
            'due_payments' => $duePayments,
            'new_enrollments' => $newEnrollments,
            'trends' => [
                'total_members' => percent_change($totalMembers, max(1, $totalMembers - $newEnrollments)),
                'active_members' => percent_change($activeMembers, $prevActive ?: $activeMembers),
                'monthly_revenue' => percent_change($monthlyRevenue, $prevRevenue),
                'due_payments' => percent_change($duePayments, $prevDue ?: $duePayments),
                'new_enrollments' => percent_change($newEnrollments, $prevEnrollments),
            ],
            'membership_status' => $membershipStatus,
            'membership_total' => array_sum($membershipStatus),
            'recent_members' => $recentMembers,
            'pending_fees' => $pendingFeeMembers,
            'popular_classes' => $popularClasses,
            'trainer_load' => $trainerLoad,
            'self_training' => $selfTrainingCount,
            'due_count' => $dueCount,
            'biometric' => $this->biometricStatus(),
        ];

        return view('dashboard.index', compact('stats', 'charts'));
    }

    /**
     * @return array{connected:bool,label:string,title:string,ip:?string}
     */
    private function biometricStatus(): array
    {
        $device = ZktecoDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->latest('last_seen_at')
            ->first();

        if (! $device) {
            return [
                'connected' => false,
                'label' => 'No biometric device',
                'title' => 'Connect a ZKTeco K50 under Biometric Device',
                'ip' => null,
            ];
        }

        // Strict: only "connected" if seen in the last ~20s (live probe updates this)
        if ($device->isConnected(20)) {
            return [
                'connected' => true,
                'label' => 'Biometric connected',
                'title' => 'K50 online' . ($device->ip_address ? ' · ' . $device->ip_address : ''),
                'ip' => $device->ip_address,
            ];
        }

        return [
            'connected' => false,
            'label' => 'Biometric offline',
            'title' => 'Unreachable · ' . ($device->ip_address ?? 'no IP')
                . ' · last seen ' . ($device->last_seen_at?->diffForHumans() ?? 'never'),
            'ip' => $device->ip_address,
        ];
    }

    private function rangeBounds(string $range): array
    {
        $to = now()->endOfDay();

        return match ($range) {
            'month' => [now()->startOfMonth()->startOfDay(), $to],
            'year' => [now()->startOfYear()->startOfDay(), $to],
            default => [now()->startOfWeek()->startOfDay(), $to],
        };
    }

    private function previousBounds(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;

        return [
            $from->copy()->subDays($days)->startOfDay(),
            $from->copy()->subDay()->endOfDay(),
        ];
    }

    private function revenueSeries(string $period): array
    {
        if ($period === 'month') {
            $from = now()->startOfMonth();
            $to = now();
            $labels = [];
            $values = [];

            foreach (CarbonPeriod::create($from, $to) as $day) {
                $labels[] = $day->format('M j');
                $values[] = (float) Payment::query()
                    ->where('status', 'completed')
                    ->whereDate('payment_date', $day->toDateString())
                    ->sum('amount');
            }

            return compact('labels', 'values');
        }

        $from = now()->startOfWeek();
        $labels = [];
        $values = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->format('D j');
            $values[] = (float) Payment::query()
                ->where('status', 'completed')
                ->whereDate('payment_date', $day->toDateString())
                ->sum('amount');
        }

        return compact('labels', 'values');
    }

    private function incomeExpenseSeries(string $period): array
    {
        if ($period === 'year') {
            $labels = [];
            $income = [];
            $expenses = [];

            for ($m = 1; $m <= 12; $m++) {
                $labels[] = Carbon::create(null, $m, 1)->format('M');
                $income[] = (float) Payment::query()
                    ->where('status', 'completed')
                    ->whereYear('payment_date', now()->year)
                    ->whereMonth('payment_date', $m)
                    ->sum('amount');
                $expenses[] = (float) Expense::query()
                    ->where('status', 'approved')
                    ->whereYear('expense_date', now()->year)
                    ->whereMonth('expense_date', $m)
                    ->sum('amount');
            }

            return compact('labels', 'income', 'expenses');
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $labels = [];
        $income = [];
        $expenses = [];
        $week = 1;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $weekEnd = $cursor->copy()->endOfWeek();
            if ($weekEnd->gt($end)) {
                $weekEnd = $end->copy();
            }

            $labels[] = 'Week ' . $week;
            $income[] = (float) Payment::query()
                ->where('status', 'completed')
                ->whereBetween('payment_date', [$cursor->toDateString(), $weekEnd->toDateString()])
                ->sum('amount');
            $expenses[] = (float) Expense::query()
                ->where('status', 'approved')
                ->whereBetween('expense_date', [$cursor->toDateString(), $weekEnd->toDateString()])
                ->sum('amount');

            $cursor = $weekEnd->copy()->addDay()->startOfDay();
            $week++;
            if ($week > 6) {
                break;
            }
        }

        return compact('labels', 'income', 'expenses');
    }
}
