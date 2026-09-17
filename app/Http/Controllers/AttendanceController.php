<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = Attendance::query()
            ->with(['member.activeSubscription.plan', 'member.trainer'])
            ->latest('attendance_date')
            ->latest('check_in');

        if ($search = $request->get('search')) {
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('member_code', 'like', "%{$search}%");
            });
        }

        // No date in the URL means a fresh visit: show today. An explicitly
        // cleared date (?date=) shows every record.
        $date = $request->has('date') ? $request->get('date') : now()->toDateString();

        if ($date) {
            $query->whereDate('attendance_date', $date);
        }

        if ($method = $request->get('method')) {
            $query->where('method', $method);
        }

        $attendances = $query->paginate(25)->withQueryString();

        // Infinite scroll: return only the next rows
        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('attendance.partials.rows', compact('attendances'))->render(),
                'next_page' => $attendances->hasMorePages() ? $attendances->currentPage() + 1 : null,
                'loaded' => $attendances->lastItem() ?? 0,
                'total' => $attendances->total(),
            ]);
        }

        $today = now()->toDateString();
        $stats = [
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

        return view('attendance.index', compact('attendances', 'stats', 'date'));
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
