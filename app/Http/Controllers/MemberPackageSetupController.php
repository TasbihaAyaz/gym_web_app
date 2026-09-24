<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\MembershipPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberPackageSetupController extends Controller
{
    public function __construct(
        private readonly MembershipPaymentService $membershipPayments,
    ) {
    }

    public function index(Request $request): View
    {
        $member = null;
        if ($request->filled('member_id')) {
            $member = Member::with([
                'trainer',
                'activeSubscription.plan',
                'subscriptions' => fn ($q) => $q->with('plan')->orderByDesc('end_date')->orderByDesc('id'),
                'payments' => fn ($q) => $q->with('plan')->latest('payment_date')->latest('id')->limit(12),
            ])->find($request->integer('member_id'));
        }

        return view('members.package-setup', [
            'member' => $member,
            'plans' => $this->activePackages(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'defaultAccountId' => $this->membershipPayments->defaultCashAccountId(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['members' => []]);
        }

        $members = Member::query()
            ->with('activeSubscription.plan')
            ->where(function ($query) use ($q) {
                $query->where('device_user_id', 'like', "%{$q}%")
                    ->orWhere('member_code', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) like ?", ["%{$q}%"]);
            })
            ->orderByRaw("CAST(NULLIF(device_user_id, '') AS UNSIGNED) DESC")
            ->limit(20)
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->full_name,
                'bio_id' => $m->device_user_id ?: '',
                'code' => $m->member_code,
                'phone' => $m->phone,
                'package' => $m->activeSubscription?->plan?->name,
            ]);

        return response()->json(['members' => $members]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'membership_plan_id' => ['required', 'exists:membership_plans,id'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'change_type' => ['required', 'in:new,renew,upgrade,downgrade,transfer'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'record_payment' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'cheque_no' => ['nullable', 'string', 'max:100'],
            'balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        $start = $data['effective_date'];
        $end = Carbon::parse($start)->addDays((int) $plan->duration_days)->toDateString();
        $monthlyFee = round((float) $data['monthly_fee'], 2);
        $recordPayment = $request->boolean('record_payment');
        $paymentAmount = round((float) ($data['payment_amount'] ?? 0), 2);
        $balance = array_key_exists('balance', $data) && $data['balance'] !== null && $data['balance'] !== ''
            ? round((float) $data['balance'], 2)
            : max(0, round((float) $plan->price - $monthlyFee, 2));
        $changeLabel = ucfirst($data['change_type']);
        $notes = trim(implode("\n", array_filter([
            'Package setup · '.$changeLabel,
            $data['remarks'] ?? null,
            ! empty($data['cheque_no']) ? 'Cheque / Ref: '.$data['cheque_no'] : null,
        ])));

        DB::transaction(function () use (
            $member,
            $plan,
            $start,
            $end,
            $monthlyFee,
            $recordPayment,
            $paymentAmount,
            $balance,
            $notes,
            $data,
        ) {
            if ($recordPayment && $paymentAmount >= 0.01) {
                $this->membershipPayments->recordPackageFee(
                    $member,
                    $plan,
                    $paymentAmount,
                    $start,
                    $end,
                    'active',
                    max(0, round((float) $plan->price - $paymentAmount - $balance, 2)),
                    null,
                    $data['account_id'] ?? null,
                    'cash',
                    $start,
                    $data['cheque_no'] ?? null,
                    $notes,
                    auth()->id(),
                    false,
                    $balance,
                );
            } else {
                $this->membershipPayments->syncSubscription(
                    $member,
                    $plan,
                    $monthlyFee,
                    $start,
                    $end,
                    'active',
                    $balance,
                );
            }
        });

        return redirect()
            ->route('members.package-setup', ['member_id' => $member->id])
            ->with('success', 'Package updated for '.$member->full_name.'.');
    }

    private function activePackages()
    {
        $catalogOrder = collect(config('membership_packages', []))
            ->map(fn ($row) => Str::slug($row['name']))
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
}
