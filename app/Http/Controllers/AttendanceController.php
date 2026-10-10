<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        // No date in the URL means a fresh visit: show today. An explicitly
        // cleared date (?date=) shows every record.
        $date = $request->has('date') ? $request->get('date') : now()->toDateString();
        $person = $request->get('person', ''); // '' = all members attendance, 'staff' = staff board
        $isStaffView = $person === 'staff';

        if ($isStaffView) {
            $staffDate = $date ?: now()->toDateString();
            $staffRows = $this->staffAttendanceBoard($staffDate, $request->get('search'));

            if ($request->boolean('partial')) {
                return response()->json([
                    'html' => view('attendance.partials.staff-rows', [
                        'staffRows' => $staffRows,
                        'date' => $staffDate,
                    ])->render(),
                    'next_page' => null,
                    'loaded' => $staffRows->count(),
                    'total' => $staffRows->count(),
                ]);
            }

            $stats = $this->stats();

            return view('attendance.index', [
                'attendances' => null,
                'staffRows' => $staffRows,
                'isStaffView' => true,
                'stats' => $stats,
                'date' => $staffDate,
                'staffCheckinTime' => $this->staffCheckinTime(),
            ]);
        }

        $query = Attendance::query()
            ->with(['member.activeSubscription.plan', 'member.trainer'])
            ->whereHas('member', fn ($q) => $q->where('is_staff', false))
            ->latest('attendance_date')
            ->latest('check_in');

        if ($search = $request->get('search')) {
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('member_code', 'like', "%{$search}%")
                    ->orWhere('device_user_id', 'like', "%{$search}%");
            });
        }

        if ($date) {
            $query->whereDate('attendance_date', $date);
        }

        if ($method = $request->get('method')) {
            $query->where('method', $method);
        }

        $attendances = $query->paginate(25)->withQueryString();

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('attendance.partials.rows', compact('attendances'))->render(),
                'next_page' => $attendances->hasMorePages() ? $attendances->currentPage() + 1 : null,
                'loaded' => $attendances->lastItem() ?? 0,
                'total' => $attendances->total(),
            ]);
        }

        return view('attendance.index', [
            'attendances' => $attendances,
            'staffRows' => collect(),
            'isStaffView' => false,
            'stats' => $this->stats(),
            'date' => $date,
            'staffCheckinTime' => $this->staffCheckinTime(),
        ]);
    }

    public function create(): View
    {
        return view('attendance.create', [
            'members' => Member::where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Attendance::create($data);

        return redirect()->route('attendance.index')->with('success', 'Attendance recorded successfully.');
    }

    public function show(Attendance $attendance): View
    {
        $attendance->load('member');

        return view('attendance.show', compact('attendance'));
    }

    public function edit(Attendance $attendance): View
    {
        return view('attendance.edit', [
            'attendance' => $attendance,
            'members' => Member::orderBy('first_name')->get(),
        ]);
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $attendance->update($this->validated($request, $attendance));

        return redirect()->route('attendance.index')->with('success', 'Attendance updated successfully.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $attendance->delete();

        return redirect()->route('attendance.index')->with('success', 'Attendance deleted successfully.');
    }

    /**
     * Staff / trainer day board: Present, Late, or Off (no punch).
     */
    private function staffAttendanceBoard(string $date, ?string $search = null): Collection
    {
        $cutoff = $this->staffCheckinTime(); // H:i
        $staff = Member::query()
            ->where('is_staff', true)
            ->whereIn('status', ['active', 'leave'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('member_code', 'like', "%{$search}%")
                        ->orWhere('device_user_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $attendanceByMember = Attendance::query()
            ->whereDate('attendance_date', $date)
            ->whereIn('member_id', $staff->pluck('id'))
            ->get()
            ->keyBy('member_id');

        return $staff->map(function (Member $member) use ($attendanceByMember, $cutoff, $date) {
            $row = $attendanceByMember->get($member->id);
            $checkIn = $row?->check_in;
            $status = 'off';
            $statusLabel = 'Off';

            if ($checkIn) {
                $checkInTime = Carbon::parse($date.' '.$checkIn)->format('H:i');
                if ($checkInTime > $cutoff) {
                    $status = 'late';
                    $statusLabel = 'Late';
                } else {
                    $status = 'present';
                    $statusLabel = 'On time';
                }
            }

            return (object) [
                'member' => $member,
                'attendance' => $row,
                'check_in' => $checkIn,
                'check_out' => $row?->check_out,
                'method' => $row?->method,
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        })->values();
    }

    private function staffCheckinTime(): string
    {
        $raw = (string) Setting::getValue('staff_checkin_time', '10:00');
        if (! preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
            return '10:00';
        }

        [$h, $m] = array_map('intval', explode(':', $raw));

        return sprintf('%02d:%02d', min(23, max(0, $h)), min(59, max(0, $m)));
    }

    private function stats(): array
    {
        $today = now()->toDateString();

        return [
            'today' => Attendance::whereDate('attendance_date', $today)->count(),
            'week' => Attendance::where('attendance_date', '>=', now()->subDays(6)->toDateString())->count(),
            'month' => Attendance::whereMonth('attendance_date', now()->month)
                ->whereYear('attendance_date', now()->year)
                ->count(),
            'members_today' => Attendance::whereDate('attendance_date', $today)
                ->whereNotNull('check_in')
                ->distinct('member_id')
                ->count('member_id'),
        ];
    }

    private function validated(Request $request, ?Attendance $attendance = null): array
    {
        return $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('attendances', 'attendance_date')
                    ->where(fn ($q) => $q->where('member_id', $request->input('member_id')))
                    ->ignore($attendance?->id),
            ],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'method' => ['required', 'in:manual,biometric,app'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
