<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Trainer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $report = $this->reportType($request);
        $tabs = $this->tabs();

        $base = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'report' => $report,
            'reportTabs' => $tabs,
        ];

        return match ($report) {
            'members' => view('reports.members', array_merge($base, $this->membersReport($from, $to))),
            'active' => view('reports.active', array_merge($base, $this->activeMembersReport($from, $to))),
            'pending_fees' => view('reports.pending_fees', array_merge($base, $this->pendingFeesReport($from, $to))),
            'expenses' => view('reports.expenses', array_merge($base, $this->expensesReport($from, $to))),
            'earnings' => view('reports.earnings', array_merge($base, $this->earningsReport($from, $to))),
            default => view('reports.index', array_merge($base, $this->overviewReport($from, $to))),
        };
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $report = $this->reportType($request);

        return match ($report) {
            'active' => $this->exportActive($from, $to),
            'pending_fees' => $this->exportPendingFees($from, $to),
            'expenses' => $this->exportExpenses($from, $to),
            'earnings' => $this->exportEarnings($from, $to),
            default => $this->exportMembers($from, $to),
        };
    }

    private function tabs(): array
    {
        return auth()->user()?->allowedReportTabs() ?? [];
    }

    private function reportType(Request $request): string
    {
        $tabs = $this->tabs();
        abort_if($tabs === [], 403, 'You do not have permission to view reports.');

        $requested = (string) $request->get('report', array_key_first($tabs));

        abort_unless(
            array_key_exists($requested, $tabs),
            403,
            'You do not have permission to view this report.'
        );

        return $requested;
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function dateRange(Request $request): array
    {
        $from = Carbon::parse($request->get('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->get('to', now()->toDateString()))->endOfDay();

        if ($from->gt($to)) {
            return [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function overviewReport(Carbon $from, Carbon $to): array
    {
        $paymentsQuery = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]);

        $expensesQuery = Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);

        $attendanceQuery = Attendance::whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]);

        $income = (float) (clone $paymentsQuery)->sum('amount');
        $expenseTotal = (float) (clone $expensesQuery)->sum('amount');
        $asOf = $to->toDateString();

        $activeFee = Member::where('status', 'active')
            ->whereHas('activeSubscription', fn ($q) => $q->whereDate('end_date', '>=', $asOf))
            ->count();

        $pendingFee = $this->pendingFeeMembers($asOf)->count();

        $stats = [
            'members' => Member::count(),
            'active_members' => Member::where('status', 'active')->count(),
            'active_fee' => $activeFee,
            'pending_fee' => $pendingFee,
            'new_members' => Member::whereBetween('joined_at', [$from->toDateString(), $to->toDateString()])->count(),
            'trainers' => Trainer::where('status', 'active')->count(),
            'classes' => GymClass::where('status', 'active')->count(),
            'attendance' => (clone $attendanceQuery)->count(),
            'income' => $income,
            'expenses' => $expenseTotal,
            'net' => $income - $expenseTotal,
            'invoices_due' => $pendingFee,
            'due_amount' => (float) $this->pendingFeeMembers($asOf)->sum(function (Member $member) {
                $sub = $member->activeSubscription;

                return (float) ($sub?->plan?->price ?? $sub?->amount_paid ?? 0);
            }),
        ];

        $membershipStatus = [
            'active' => Member::where('status', 'active')->count(),
            'leave' => Member::where('status', 'leave')->count(),
            'pending' => Member::where('status', 'pending')->count(),
            'cancelled' => Member::where('status', 'cancelled')->count(),
        ];

        $trendEnd = $to->copy()->startOfDay();
        $trendStart = $trendEnd->copy()->subDays(6);
        $attendanceTrend = [];
        foreach (CarbonPeriod::create($trendStart, $trendEnd) as $day) {
            $attendanceTrend[] = [
                'label' => $day->format('M d'),
                'value' => Attendance::whereDate('attendance_date', $day->toDateString())->count(),
            ];
        }

        $financeTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $financeTrend[] = [
                'label' => $month->format('M Y'),
                'income' => (float) Payment::where('status', 'completed')
                    ->whereMonth('payment_date', $month->month)
                    ->whereYear('payment_date', $month->year)
                    ->sum('amount'),
                'expenses' => (float) Expense::where('status', 'approved')
                    ->whereMonth('expense_date', $month->month)
                    ->whereYear('expense_date', $month->year)
                    ->sum('amount'),
            ];
        }

        $topClasses = GymClass::with('trainer')
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->take(5)
            ->get();

        $paymentMethods = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->select('method', DB::raw('COUNT(*) as total'), DB::raw('SUM(amount) as amount'))
            ->groupBy('method')
            ->orderByDesc('amount')
            ->get();

        $expenseByCategory = Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->select('category', DB::raw('SUM(amount) as amount'), DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get();

        return compact(
            'stats',
            'membershipStatus',
            'attendanceTrend',
            'financeTrend',
            'topClasses',
            'paymentMethods',
            'expenseByCategory'
        );
    }

    private function membersReport(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $members = Member::query()
            ->with(['activeSubscription.plan'])
            ->where(function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('joined_at', [$fromDate, $toDate])
                    ->orWhereHas('activeSubscription', function ($s) use ($fromDate, $toDate) {
                        $s->where(function ($w) use ($fromDate, $toDate) {
                            $w->whereBetween('start_date', [$fromDate, $toDate])
                                ->orWhereBetween('end_date', [$fromDate, $toDate])
                                ->orWhere(function ($x) use ($fromDate, $toDate) {
                                    $x->whereDate('start_date', '<=', $fromDate)
                                        ->whereDate('end_date', '>=', $toDate);
                                });
                        });
                    })
                    ->orWhereHas('payments', function ($p) use ($fromDate, $toDate) {
                        $p->where('status', 'completed')
                            ->whereBetween('payment_date', [$fromDate, $toDate]);
                    });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $paidInRange = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->select('member_id', DB::raw('SUM(amount) as paid'))
            ->groupBy('member_id')
            ->pluck('paid', 'member_id');

        $stats = [
            'total' => $members->count(),
            'active' => $members->where('status', 'active')->count(),
            'revenue' => (float) $paidInRange->sum(),
        ];

        return compact('members', 'paidInRange', 'stats');
    }

    private function activeMembersReport(Carbon $from, Carbon $to): array
    {
        $asOf = $to->toDateString();

        $members = Member::query()
            ->with(['activeSubscription.plan'])
            ->where('status', 'active')
            ->whereHas('activeSubscription', function ($q) use ($asOf, $from) {
                $q->whereDate('end_date', '>=', $asOf)
                    ->whereDate('start_date', '<=', $asOf);
                // Optionally joined/started fee overlapping selected window
                $q->where(function ($w) use ($from, $asOf) {
                    $w->whereBetween('start_date', [$from->toDateString(), $asOf])
                        ->orWhereBetween('end_date', [$from->toDateString(), $asOf])
                        ->orWhere(function ($x) use ($from, $asOf) {
                            $x->whereDate('start_date', '<=', $from->toDateString())
                                ->whereDate('end_date', '>=', $asOf);
                        });
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $stats = [
            'total' => $members->count(),
            'fee_amount' => (float) $members->sum(fn ($m) => (float) ($m->activeSubscription?->amount_paid ?? 0)),
        ];

        return compact('members', 'stats', 'asOf');
    }

    private function pendingFeesReport(Carbon $from, Carbon $to): array
    {
        $asOf = $to->toDateString();
        $members = $this->pendingFeeMembers($asOf)
            ->filter(function (Member $member) use ($from, $asOf) {
                $sub = $member->activeSubscription;
                if (! $sub?->end_date) {
                    return false;
                }
                $end = $sub->end_date->toDateString();

                return $end >= $from->toDateString() && $end <= $asOf;
            })
            ->values();

        $rows = $members->map(function (Member $member) use ($asOf) {
            $sub = $member->activeSubscription;
            $daysOverdue = $sub?->end_date
                ? Carbon::parse($sub->end_date->toDateString())->diffInDays(Carbon::parse($asOf))
                : 0;
            $dueBalance = (float) ($sub?->plan?->price ?? $sub?->amount_paid ?? 0);

            return [
                'member' => $member,
                'package' => $sub?->plan?->name,
                'fee_start' => $sub?->start_date,
                'fee_end' => $sub?->end_date,
                'days_overdue' => $daysOverdue,
                'due_balance' => $dueBalance,
                'reason' => 'Fee expired',
            ];
        });

        $stats = [
            'total' => $rows->count(),
            'due_amount' => (float) $rows->sum('due_balance'),
            'avg_overdue' => $rows->count() ? round($rows->avg('days_overdue')) : 0,
        ];

        return compact('rows', 'stats', 'asOf');
    }

    private function expensesReport(Carbon $from, Carbon $to): array
    {
        $expenses = Expense::query()
            ->with(['account', 'recorder'])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->latest('expense_date')
            ->latest('id')
            ->get();

        $byCategory = $expenses
            ->groupBy('category')
            ->map(fn (Collection $group) => [
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
                'approved' => (float) $group->where('status', 'approved')->sum('amount'),
            ])
            ->sortByDesc('amount');

        $stats = [
            'total' => $expenses->count(),
            'amount' => (float) $expenses->sum('amount'),
            'approved' => (float) $expenses->where('status', 'approved')->sum('amount'),
            'pending' => (float) $expenses->where('status', 'pending')->sum('amount'),
            'rejected' => (float) $expenses->where('status', 'rejected')->sum('amount'),
        ];

        return compact('expenses', 'byCategory', 'stats');
    }

    private function earningsReport(Carbon $from, Carbon $to): array
    {
        $payments = Payment::query()
            ->with(['member', 'plan', 'account', 'receiver'])
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $expenses = Expense::query()
            ->with(['account', 'recorder'])
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        $cashReceived = (float) $payments->filter(fn (Payment $p) => $this->isCashMethod($p->method))->sum('amount');
        $bankReceived = (float) $payments->reject(fn (Payment $p) => $this->isCashMethod($p->method))->sum('amount');

        $cashExpense = (float) $expenses->filter(fn (Expense $e) => $this->isCashMethod($e->payment_method))->sum('amount');
        $bankExpense = (float) $expenses->reject(fn (Expense $e) => $this->isCashMethod($e->payment_method))->sum('amount');

        $cashCommission = 0.0;
        $bankCommission = 0.0;

        $cashInHand = $cashReceived - $cashExpense - $cashCommission;
        $bankNet = $bankReceived - $bankExpense - $bankCommission;
        $totalReceipts = $cashReceived + $bankReceived;
        $totalExpenses = $cashExpense + $bankExpense;
        $totalEarning = $totalReceipts - $totalExpenses - $cashCommission - $bankCommission;

        $paymentRows = $payments->map(function (Payment $payment) {
            $member = $payment->member;
            $joined = $member?->joined_at?->toDateString();
            $payDate = $payment->payment_date?->toDateString();
            $isAdmission = str_starts_with((string) $payment->reference, 'ADM-MEMBER-')
                || str_starts_with((string) $payment->notes, 'Admission fee');
            $isNew = $joined && $payDate && $joined === $payDate;

            $details = [];
            if ($payment->plan?->name) {
                $details[] = $payment->plan->name;
            }
            if ($payment->fee_start_date && $payment->fee_end_date) {
                $details[] = $payment->fee_start_date->format('d-M-Y').' → '.$payment->fee_end_date->format('d-M-Y');
            }
            if ($payment->notes) {
                $details[] = $payment->notes;
            }

            return [
                'kind' => 'receipt',
                'sort_date' => $payment->payment_date?->toDateString(),
                'sort_id' => $payment->id,
                'date' => $payment->payment_date,
                'type' => $isAdmission
                    ? 'Admission Fee'
                    : ($isNew ? 'New Member' : ($payment->plan?->name ? 'Fee Renewal' : 'Fee Payment')),
                'type_class' => 'active',
                'voucher' => $payment->payment_number,
                'account_label' => $member
                    ? ('#'.($member->device_user_id ?: $member->id).' '.$member->full_name)
                    : ($payment->account?->name ?: '—'),
                'payment_label' => $this->paymentBookLabel($payment->method),
                'details' => $details ? implode(' · ', $details) : '—',
                'amount' => (float) $payment->amount,
                'amount_signed' => (float) $payment->amount,
            ];
        });

        $expenseRows = $expenses->map(function (Expense $expense) {
            $details = [];
            if ($expense->vendor) {
                $details[] = 'Vendor: '.$expense->vendor;
            }
            if ($expense->description) {
                $details[] = $expense->description;
            }
            if ($expense->recorder?->name) {
                $details[] = 'By '.$expense->recorder->name;
            }

            return [
                'kind' => 'expense',
                'sort_date' => $expense->expense_date?->toDateString(),
                'sort_id' => $expense->id,
                'date' => $expense->expense_date,
                'type' => 'Expense',
                'type_class' => 'expired',
                'voucher' => $expense->expense_number,
                'account_label' => $expense->category.($expense->account?->name ? ' · '.$expense->account->name : ''),
                'payment_label' => $this->paymentBookLabel($expense->payment_method),
                'details' => $details ? implode(' · ', $details) : ($expense->title ?: '—'),
                'amount' => (float) $expense->amount,
                'amount_signed' => -1 * (float) $expense->amount,
            ];
        });

        $rows = $paymentRows
            ->concat($expenseRows)
            ->sortBy([
                ['sort_date', 'asc'],
                ['kind', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values()
            ->map(function (array $row, int $index) {
                $row['no'] = $index + 1;

                return $row;
            });

        $periodDisplay = $from->isSameDay($to)
            ? $from->format('d-M-Y')
            : $from->format('d-M-Y').' → '.$to->format('d-M-Y');

        $gym = [
            'name' => Setting::getValue('gym_name', 'Fit Generation'),
            'address' => Setting::getValue('gym_address', ''),
            'phone' => Setting::getValue('gym_phone', ''),
        ];

        $summary = [
            'cash_received' => $cashReceived,
            'bank_received' => $bankReceived,
            'cash_expense' => $cashExpense,
            'bank_expense' => $bankExpense,
            'cash_commission' => $cashCommission,
            'bank_commission' => $bankCommission,
            'cash_in_hand' => $cashInHand,
            'bank_net' => $bankNet,
            'total_receipts' => $totalReceipts,
            'total_expenses' => $totalExpenses,
            'total_earning' => $totalEarning,
        ];

        return [
            'periodLabel' => 'SALES REPORT',
            'title' => 'Sales Report',
            'periodDisplay' => $periodDisplay,
            'gym' => $gym,
            'rows' => $rows,
            'summary' => $summary,
            'printedAt' => now(),
            'printedBy' => auth()->user()?->name ?? 'Staff',
        ];
    }

    private function exportEarnings(Carbon $from, Carbon $to): StreamedResponse
    {
        $data = $this->earningsReport($from, $to);
        $filename = 'sales-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return $this->csvDownload($filename, function ($out) use ($data, $from, $to) {
            fputcsv($out, ['Report', $data['title']]);
            fputcsv($out, ['From', $from->toDateString(), 'To', $to->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['Cash received', $data['summary']['cash_received']]);
            fputcsv($out, ['Bank received', $data['summary']['bank_received']]);
            fputcsv($out, ['Cash expense', $data['summary']['cash_expense']]);
            fputcsv($out, ['Bank expense', $data['summary']['bank_expense']]);
            fputcsv($out, ['Cash in hand', $data['summary']['cash_in_hand']]);
            fputcsv($out, ['Bank net', $data['summary']['bank_net']]);
            fputcsv($out, ['Total earning', $data['summary']['total_earning']]);
            fputcsv($out, []);
            fputcsv($out, ['#', 'Date', 'Type', 'Voucher', 'Account / Detail', 'Payment', 'Details', 'Amount ('.currency_code().')']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['no'],
                    $row['date']?->format('Y-m-d'),
                    $row['type'],
                    $row['voucher'],
                    $row['account_label'],
                    $row['payment_label'],
                    $row['details'] ?? '',
                    number_format($row['amount_signed'], 2, '.', ''),
                ]);
            }
        });
    }

    private function isCashMethod(?string $method): bool
    {
        $method = strtolower(trim((string) $method));

        return $method === '' || $method === 'cash';
    }

    private function paymentBookLabel(?string $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'cash', '' => 'CASH BOOK',
            'online' => 'ONLINE',
            'card' => 'CARD',
            'bank_transfer' => 'BANK TRANSFER',
            default => strtoupper(str_replace('_', ' ', (string) $method)),
        };
    }

    /** @return Collection<int, Member> */
    private function pendingFeeMembers(string $asOf): Collection
    {
        return Member::query()
            ->with(['activeSubscription.plan'])
            ->where('status', '!=', 'cancelled')
            ->whereHas('activeSubscription', function ($q) use ($asOf) {
                $q->whereDate('end_date', '<', $asOf);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function exportMembers(Carbon $from, Carbon $to): StreamedResponse
    {
        $data = $this->membersReport($from, $to);
        $filename = 'members-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return $this->csvDownload($filename, function ($out) use ($data, $from, $to) {
            fputcsv($out, ['From', 'To', 'Member Code', 'Name', 'Phone', 'Status', 'Package', 'Fee Start', 'Fee End', 'Joined', 'Paid In Range ('.currency_code().')']);
            foreach ($data['members'] as $member) {
                $sub = $member->activeSubscription;
                fputcsv($out, [
                    $from->toDateString(),
                    $to->toDateString(),
                    $member->member_code,
                    $member->full_name,
                    $member->phone,
                    $member->status,
                    $sub?->plan?->name,
                    $sub?->start_date?->format('Y-m-d'),
                    $sub?->end_date?->format('Y-m-d'),
                    $member->joined_at?->format('Y-m-d'),
                    number_format((float) ($data['paidInRange'][$member->id] ?? 0), 2, '.', ''),
                ]);
            }
        });
    }

    private function exportActive(Carbon $from, Carbon $to): StreamedResponse
    {
        $data = $this->activeMembersReport($from, $to);
        $filename = 'active-members-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return $this->csvDownload($filename, function ($out) use ($data, $from, $to) {
            fputcsv($out, ['From', 'To', 'As Of', 'Member Code', 'Name', 'Phone', 'Package', 'Package Price', 'Amount Paid', 'Fee Start', 'Fee End']);
            foreach ($data['members'] as $member) {
                $sub = $member->activeSubscription;
                fputcsv($out, [
                    $from->toDateString(),
                    $to->toDateString(),
                    $data['asOf'],
                    $member->member_code,
                    $member->full_name,
                    $member->phone,
                    $sub?->plan?->name,
                    number_format((float) ($sub?->plan?->price ?? 0), 2, '.', ''),
                    number_format((float) ($sub?->amount_paid ?? 0), 2, '.', ''),
                    $sub?->start_date?->format('Y-m-d'),
                    $sub?->end_date?->format('Y-m-d'),
                ]);
            }
        });
    }

    private function exportPendingFees(Carbon $from, Carbon $to): StreamedResponse
    {
        $data = $this->pendingFeesReport($from, $to);
        $filename = 'fee-pending-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return $this->csvDownload($filename, function ($out) use ($data, $from, $to) {
            fputcsv($out, ['From', 'To', 'As Of', 'Member Code', 'Name', 'Phone', 'Status', 'Package', 'Fee Start', 'Fee End', 'Days Overdue', 'Due Balance', 'Reason']);
            foreach ($data['rows'] as $row) {
                $member = $row['member'];
                fputcsv($out, [
                    $from->toDateString(),
                    $to->toDateString(),
                    $data['asOf'],
                    $member->member_code,
                    $member->full_name,
                    $member->phone,
                    $member->status,
                    $row['package'],
                    $row['fee_start']?->format('Y-m-d'),
                    $row['fee_end']?->format('Y-m-d'),
                    $row['days_overdue'],
                    number_format($row['due_balance'], 2, '.', ''),
                    $row['reason'],
                ]);
            }
        });
    }

    private function exportExpenses(Carbon $from, Carbon $to): StreamedResponse
    {
        $data = $this->expensesReport($from, $to);
        $filename = 'expenses-detail-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return $this->csvDownload($filename, function ($out) use ($data, $from, $to) {
            fputcsv($out, ['From', 'To', 'Expense #', 'Date', 'Title', 'Category', 'Vendor', 'Method', 'Account', 'Status', 'Amount ('.currency_code().')', 'Recorded By', 'Description']);
            foreach ($data['expenses'] as $expense) {
                fputcsv($out, [
                    $from->toDateString(),
                    $to->toDateString(),
                    $expense->expense_number,
                    $expense->expense_date?->format('Y-m-d'),
                    $expense->title,
                    $expense->category,
                    $expense->vendor,
                    $expense->payment_method,
                    $expense->account?->name,
                    $expense->status,
                    number_format((float) $expense->amount, 2, '.', ''),
                    $expense->recorder?->name,
                    $expense->description,
                ]);
            }
        });
    }

    private function csvDownload(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $writer($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
