<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Setting;
use App\Models\Trainer;
use App\Services\MembershipPaymentService;
use App\Services\ZktecoUserSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): View|JsonResponse
    {
        $query = Member::query()
            ->with(['activeSubscription.plan', 'trainer'])
            ->orderByRaw("CAST(NULLIF(device_user_id, '') AS UNSIGNED) DESC")
            ->orderByDesc('id');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('member_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($gender = $request->get('gender')) {
            $query->where('gender', $gender);
        }

        if ($fee = $request->get('fee_status')) {
            if ($fee === 'active') {
                $query->whereHas('activeSubscription', fn ($q) => $q->whereDate('end_date', '>=', now()->toDateString()));
            } elseif ($fee === 'expiring') {
                $query->whereHas('activeSubscription', function ($q) {
                    $q->whereDate('end_date', '>=', now()->toDateString())
                        ->whereDate('end_date', '<=', now()->addDays(7)->toDateString());
                });
            } elseif ($fee === 'expired') {
                $query->whereHas('activeSubscription', fn ($q) => $q->whereDate('end_date', '<', now()->toDateString()));
            } elseif ($fee === 'balance') {
                $query->whereHas('activeSubscription', fn ($q) => $q->where('balance_due', '>', 0));
            }
        }

        $members = $query->paginate(25)->withQueryString();

        // Infinite scroll: return only the next rows
        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('members.partials.rows', compact('members'))->render(),
                'next_page' => $members->hasMorePages() ? $members->currentPage() + 1 : null,
                'total' => $members->total(),
            ]);
        }

        $stats = [
            'total' => Member::count(),
            'active' => Member::where('status', 'active')->count(),
            'pending' => Member::where('status', 'pending')->count(),
            'balance_due' => Member::whereHas('activeSubscription', fn ($q) => $q->where('balance_due', '>', 0))->count(),
        ];

        return view('members.index', compact('members', 'stats'));
    }

    /** JSON list of members whose fee expires in the selected date range (popup). */
    public function pendingFees(Request $request): JsonResponse
    {
        [$from, $to, $members] = $this->feeExpiryMembers($request);
        $today = now()->startOfDay();

        $rows = $members->map(function (Member $member) use ($today) {
            $sub = $member->activeSubscription;
            $end = $sub->end_date->copy()->startOfDay();
            $expired = $end->lt($today);
            $expiresToday = $end->equalTo($today);

            return [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'bio_id' => $member->device_user_id ?: '',
                'name' => $member->full_name,
                'phone' => $member->phone ?: '',
                'status' => $member->status,
                'trainer' => $member->trainer?->full_name ?? 'Self training',
                'plan' => $sub->plan?->name ?? '',
                'fee_start' => $sub->start_date?->format('Y-m-d') ?? '',
                'fee_end' => $end->toDateString(),
                'days_overdue' => $expired ? (int) $end->diffInDays($today) : 0,
                'fee_status' => $expired ? 'Expired' : ($expiresToday ? 'Expires today' : 'Upcoming'),
                'url' => route('members.show', $member),
            ];
        })->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'count' => $rows->count(),
            'rows' => $rows,
        ]);
    }

    /** Export members whose fee period expires within the selected date range. */
    public function exportPendingFees(Request $request): StreamedResponse
    {
        [$from, $to, $members] = $this->feeExpiryMembers($request);
        $today = now()->startOfDay();

        $filename = "fee-expiry-{$from}-to-{$to}-" . now()->format('His') . '.csv';

        return response()->streamDownload(function () use ($members, $from, $to, $today) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Expiry From',
                'Expiry To',
                'Member Code',
                'Member Name',
                'Phone',
                'Email',
                'Member Status',
                'Trainer',
                'Plan',
                'Fee Start',
                'Fee End',
                'Days Overdue',
                'Fee Status',
            ]);

            foreach ($members as $member) {
                $sub = $member->activeSubscription;
                $end = $sub->end_date->copy()->startOfDay();
                $expired = $end->lt($today);
                $expiresToday = $end->equalTo($today);
                $daysOverdue = $expired ? (int) $end->diffInDays($today) : '';
                $feeStatus = $expired ? 'Expired' : ($expiresToday ? 'Expires today' : 'Upcoming');

                fputcsv($out, [
                    $from,
                    $to,
                    $member->member_code,
                    $member->full_name,
                    $member->phone ?: '',
                    $member->email ?: '',
                    ucfirst($member->status),
                    $member->trainer?->full_name ?? 'Self training',
                    $sub->plan?->name ?? '',
                    $sub->start_date?->format('Y-m-d') ?? '',
                    $end->toDateString(),
                    $daysOverdue,
                    $feeStatus,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{0: string, 1: string, 2: \Illuminate\Support\Collection<int, Member>} */
    private function feeExpiryMembers(Request $request): array
    {
        $data = $request->validate([
            'expiry_from' => ['required', 'date'],
            'expiry_to' => ['required', 'date', 'after_or_equal:expiry_from'],
        ]);

        $from = Carbon::parse($data['expiry_from'])->toDateString();
        $to = Carbon::parse($data['expiry_to'])->toDateString();

        $members = Member::query()
            ->with(['activeSubscription.plan', 'trainer'])
            ->where('status', '!=', 'cancelled')
            ->whereHas('activeSubscription', function ($query) use ($from, $to) {
                $query->whereDate('end_date', '>=', $from)
                    ->whereDate('end_date', '<=', $to);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return [$from, $to, $members];
    }

    public function create(): View
    {
        return view('members.create', [
            'plans' => $this->activePackages(),
            'subscription' => null,
            'trainers' => $this->activeTrainers(),
            'defaultAdmissionFee' => $this->defaultAdmissionFee(),
        ]);
    }

    public function store(Request $request, ZktecoUserSyncService $zkUsers): RedirectResponse
    {
        $data = $this->validated($request);
        $fee = $this->feeData($data);
        unset(
            $data['avatar'],
            $data['remove_avatar'],
            $data['membership_plan_id'],
            $data['fee_start_date'],
            $data['fee_end_date'],
            $data['fee_amount_paid'],
            $data['fee_discount'],
            $data['fee_balance'],
            $data['admission_fee'],
            $data['subscription_status'],
            $data['device_user_id']
        );

        $biometricId = $this->nextBiometricId();
        $data['device_user_id'] = $biometricId;
        $data['member_code'] = 'MEM-' . str_pad($biometricId, 5, '0', STR_PAD_LEFT);
        $data['joined_at'] = $data['joined_at'] ?? now()->toDateString();
        $data['avatar'] = $this->storeImage($request, 'avatar', 'members');

        $member = null;
        DB::transaction(function () use ($data, $fee, &$member) {
            $member = Member::create($data);
            $this->syncSubscription($member, $fee);
            $this->syncAdmissionFee($member, $fee);
        });

        $devicePush = $zkUsers->pushMember($member);

        $flash = [
            'id' => $biometricId,
            'name' => $member->full_name,
            'code' => $member->member_code,
            'device_ok' => $devicePush['ok'],
            'device_message' => $devicePush['message'],
        ];

        $success = $devicePush['ok']
            ? 'Member created and saved on biometric machine as ID ' . $biometricId . '.'
            : 'Member created (ID ' . $biometricId . '), but not saved on machine: ' . $devicePush['message'];

        return redirect()
            ->route('members.index')
            ->with($devicePush['ok'] ? 'success' : 'error', $success)
            ->with('biometric_popup', $flash);
    }

    public function show(Member $member): View
    {
        $member->load([
            'subscriptions' => fn ($q) => $q->with('plan')->orderByDesc('end_date')->orderByDesc('id'),
            'attendances',
            'activeSubscription.plan',
            'trainer',
            'payments' => fn ($q) => $q->with(['plan', 'account', 'receiver'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id'),
        ]);

        $feeStats = [
            'payments' => $member->payments->count(),
            'paid' => (float) $member->payments->where('status', 'completed')->sum('amount'),
            'periods' => $member->subscriptions->count(),
        ];

        return view('members.show', compact('member', 'feeStats'));
    }

    public function edit(Member $member): View
    {
        $member->load('activeSubscription.plan');

        $existingAdmission = $member->payments()
            ->where(function ($q) use ($member) {
                $q->where('reference', 'ADM-MEMBER-'.$member->id)
                    ->orWhere('notes', 'like', 'Admission fee%');
            })
            ->latest('id')
            ->first();

        return view('members.edit', [
            'member' => $member,
            'plans' => $this->activePackages(),
            'subscription' => $member->activeSubscription,
            'trainers' => $this->activeTrainers(),
            'defaultAdmissionFee' => $existingAdmission
                ? (float) $existingAdmission->amount
                : $this->defaultAdmissionFee(),
            'existingAdmissionFee' => $existingAdmission ? (float) $existingAdmission->amount : null,
        ]);
    }

    public function update(Request $request, Member $member, ZktecoUserSyncService $zkUsers): RedirectResponse
    {
        $data = $this->validated($request, $member);
        unset(
            $data['avatar'],
            $data['remove_avatar'],
            $data['membership_plan_id'],
            $data['fee_start_date'],
            $data['fee_end_date'],
            $data['fee_amount_paid'],
            $data['fee_discount'],
            $data['fee_balance'],
            $data['admission_fee'],
            $data['subscription_status'],
            $data['device_user_id']
        );

        if ($this->clearImageIfRequested($request, 'avatar', $member)) {
            $data['avatar'] = null;
        } else {
            $data['avatar'] = $this->storeImage($request, 'avatar', 'members', $member);
        }

        // Profile-only update: never touch payments, accounts, or fee period.
        $member->update($data);

        // Keep device name in sync when member details change
        if ($member->device_user_id) {
            $zkUsers->pushMember($member->fresh());
        }

        return redirect()->route('members.index')->with('success', 'Member updated successfully.');
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:members,email,' . ($member?->id ?? 'NULL')],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,leave,pending,cancelled'],
            'joined_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'trainer_id' => ['nullable', 'integer', 'exists:trainers,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'membership_plan_id' => ['nullable', 'exists:membership_plans,id'],
            'fee_start_date' => ['nullable', 'required_with:membership_plan_id', 'date'],
            'fee_end_date' => ['nullable', 'required_with:membership_plan_id', 'date', 'after_or_equal:fee_start_date'],
            'fee_amount_paid' => ['nullable', 'numeric', 'min:0'],
            'fee_discount' => ['nullable', 'numeric', 'min:0'],
            'fee_balance' => ['nullable', 'numeric', 'min:0'],
            'admission_fee' => ['nullable', 'numeric', 'min:0'],
            'subscription_status' => ['nullable', 'in:active,leave,pending,cancelled'],
        ]);

        // Empty select = Self training
        if (empty($data['trainer_id'])) {
            $data['trainer_id'] = null;
        }

        $data['last_name'] = trim((string) ($data['last_name'] ?? ''));

        return $data;
    }

    private function feeData(array $data): array
    {
        return [
            'membership_plan_id' => $data['membership_plan_id'] ?? null,
            'fee_start_date' => $data['fee_start_date'] ?? null,
            'fee_end_date' => $data['fee_end_date'] ?? null,
            'fee_amount_paid' => $data['fee_amount_paid'] ?? null,
            'fee_discount' => $data['fee_discount'] ?? null,
            'fee_balance' => $data['fee_balance'] ?? null,
            'admission_fee' => $data['admission_fee'] ?? null,
            'subscription_status' => $data['subscription_status'] ?? 'active',
        ];
    }

    private function syncAdmissionFee(Member $member, array $fee): void
    {
        if (! array_key_exists('admission_fee', $fee) || $fee['admission_fee'] === null || $fee['admission_fee'] === '') {
            return;
        }

        $amount = (float) $fee['admission_fee'];
        if ($amount < 0.01) {
            return;
        }

        app(MembershipPaymentService::class)->recordAdmissionFee(
            $member,
            $amount,
            $member->joined_at?->toDateString() ?: now()->toDateString(),
            'ADM-MEMBER-'.$member->id,
        );
    }

    private function defaultAdmissionFee(): float
    {
        return max(0, (float) Setting::getValue('admission_fee', 0));
    }

    private function syncSubscription(Member $member, array $fee): void
    {
        if (empty($fee['membership_plan_id']) || empty($fee['fee_start_date']) || empty($fee['fee_end_date'])) {
            return;
        }

        $plan = MembershipPlan::find($fee['membership_plan_id']);
        if (! $plan) {
            return;
        }

        $explicitDiscount = array_key_exists('fee_discount', $fee) && $fee['fee_discount'] !== null && $fee['fee_discount'] !== ''
            ? (float) $fee['fee_discount']
            : null;

        $explicitBalance = array_key_exists('fee_balance', $fee) && $fee['fee_balance'] !== null && $fee['fee_balance'] !== ''
            ? (float) $fee['fee_balance']
            : null;

        $amountPaid = array_key_exists('fee_amount_paid', $fee) && $fee['fee_amount_paid'] !== null && $fee['fee_amount_paid'] !== ''
            ? (float) $fee['fee_amount_paid']
            : null;

        $price = (float) $plan->price;

        if ($amountPaid === null && $explicitDiscount !== null && $explicitBalance !== null) {
            $amountPaid = max(0, $price - $explicitDiscount - $explicitBalance);
        } elseif ($amountPaid === null && $explicitDiscount !== null) {
            $amountPaid = max(0, $price - $explicitDiscount - (float) ($explicitBalance ?? 0));
        } elseif ($amountPaid === null) {
            $amountPaid = $price;
        }

        if ($explicitDiscount === null && $explicitBalance === null) {
            // Legacy: unpaid remainder becomes discount
            $explicitDiscount = max(0, $price - $amountPaid);
            $explicitBalance = 0.0;
        } elseif ($explicitDiscount === null) {
            $explicitDiscount = max(0, $price - $amountPaid - (float) $explicitBalance);
        } elseif ($explicitBalance === null) {
            $explicitBalance = max(0, $price - $amountPaid - (float) $explicitDiscount);
        }

        app(MembershipPaymentService::class)->recordPackageFee(
            $member,
            $plan,
            $amountPaid,
            $fee['fee_start_date'],
            $fee['fee_end_date'],
            $fee['subscription_status'] ?? 'active',
            $explicitDiscount,
            'FEE-MEMBER-' . $member->id . '-' . $fee['fee_start_date'] . '-' . $fee['fee_end_date'],
            null,
            'cash',
            null,
            null,
            null,
            null,
            true,
            $explicitBalance,
        );
    }

    private function nextBiometricId(): string
    {
        $max = (int) Member::withTrashed()
            ->whereNotNull('device_user_id')
            ->where('device_user_id', 'regexp', '^[0-9]+$')
            ->selectRaw('MAX(CAST(device_user_id AS UNSIGNED)) as max_id')
            ->value('max_id');

        return (string) ($max + 1);
    }

    /** Active packages in catalog order (same list used on payments). */
    private function activePackages()
    {
        $catalogOrder = collect(config('membership_packages', []))
            ->map(fn ($row) => \Illuminate\Support\Str::slug($row['name']))
            ->values()
            ->all();

        return MembershipPlan::where('is_active', true)
            ->orderBy('price')
            ->orderBy('name')
            ->get()
            ->sortBy(function (MembershipPlan $plan) use ($catalogOrder) {
                $idx = array_search($plan->slug, $catalogOrder, true);

                return $idx === false ? 1000 + (float) $plan->price : $idx;
            })
            ->values();
    }

    private function activeTrainers()
    {
        return Trainer::where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function generateCode(): string
    {
        do {
            $code = 'MEM-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }
}
