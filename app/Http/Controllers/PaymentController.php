<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\MembershipPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MembershipPaymentService $membershipPayments,
    ) {
    }

    public function index(Request $request): View
    {
        $query = Payment::query()->with(['member', 'plan', 'account'])->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('member', function ($mq) use ($search) {
                        $mq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->get('method')) {
            $query->where('method', $method);
        }

        $payments = $query->paginate(10)->withQueryString();

        $stats = [
            'total' => Payment::count(),
            'completed' => Payment::where('status', 'completed')->count(),
            'pending' => Payment::where('status', 'pending')->count(),
            'amount' => Payment::where('status', 'completed')->sum('amount'),
        ];

        return view('payments.index', compact('payments', 'stats'));
    }

    public function create(Request $request): View
    {
        return view('payments.create', $this->formData());
    }

    /** Client fee history + package + trainer for fee payment form. */
    public function memberContext(Member $member): JsonResponse
    {
        $member->load([
            'trainer',
            'activeSubscription.plan',
            'subscriptions.plan',
            'payments' => fn ($q) => $q->latest('payment_date')->latest('id')->limit(12),
        ]);

        $today = now()->startOfDay();
        $sub = $member->activeSubscription;
        $feeStatus = 'none';
        $daysLeft = null;

        if ($sub?->end_date) {
            $end = $sub->end_date->copy()->startOfDay();
            $daysLeft = (int) $today->diffInDays($end, false);
            if ($daysLeft < 0) {
                $feeStatus = 'expired';
            } elseif ($daysLeft <= 7) {
                $feeStatus = 'expiring';
            } else {
                $feeStatus = 'valid';
            }
        }

        $suggestedStart = now()->toDateString();
        if ($sub?->end_date && $sub->end_date->copy()->startOfDay()->gte($today)) {
            $suggestedStart = $sub->end_date->copy()->addDay()->toDateString();
        }

        $duration = (int) ($sub?->plan?->duration_days ?: 30);
        $suggestedEnd = Carbon::parse($suggestedStart)->addDays($duration)->toDateString();

        return response()->json([
            'member' => [
                'id' => $member->id,
                'code' => $member->member_code,
                'bio_id' => $member->device_user_id,
                'name' => $member->full_name,
                'phone' => $member->phone,
                'status' => $member->status,
                'joined_at' => optional($member->joined_at)?->format('M d, Y'),
            ],
            'trainer' => $member->trainer
                ? [
                    'id' => $member->trainer->id,
                    'name' => $member->trainer->full_name,
                    'phone' => $member->trainer->phone,
                ]
                : null,
            'current_package' => $sub
                ? [
                    'plan_id' => $sub->membership_plan_id,
                    'plan_name' => $sub->plan?->name ?? 'Custom package',
                    'plan_price' => (float) ($sub->plan?->price ?? $sub->amount_paid ?? 0),
                    'duration_days' => $duration,
                    'start' => optional($sub->start_date)?->toDateString(),
                    'end' => optional($sub->end_date)?->toDateString(),
                    'start_label' => optional($sub->start_date)?->format('M d, Y'),
                    'end_label' => optional($sub->end_date)?->format('M d, Y'),
                    'amount_paid' => (float) $sub->amount_paid,
                    'status' => $sub->status,
                    'fee_status' => $feeStatus,
                    'days_left' => $daysLeft,
                ]
                : null,
            'suggested_fee' => [
                'start' => $suggestedStart,
                'end' => $suggestedEnd,
                'plan_id' => $sub?->membership_plan_id,
                'amount' => (float) ($sub?->plan?->price ?? 0),
            ],
            'fee_history' => $member->subscriptions
                ->sortByDesc(fn ($s) => $s->end_date?->timestamp ?? $s->id)
                ->values()
                ->take(10)
                ->map(fn ($s) => [
                    'plan' => $s->plan?->name ?? 'No package',
                    'start' => optional($s->start_date)?->format('M d, Y') ?? '—',
                    'end' => optional($s->end_date)?->format('M d, Y') ?? '—',
                    'amount_paid' => (float) $s->amount_paid,
                    'status' => $s->status,
                ])
                ->all(),
            'payments' => $member->payments->map(fn ($p) => [
                'number' => $p->payment_number,
                'date' => optional($p->payment_date)?->format('M d, Y') ?? '—',
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'status' => $p->status,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $member = Member::findOrFail($data['member_id']);
            $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
            $applyDiscount = $request->boolean('apply_package_discount', true);

            $payment = Payment::create([
                'payment_number' => $this->generateNumber(),
                'invoice_id' => null,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'account_id' => $data['account_id'] ?? null,
                'amount' => $data['amount'],
                'discount' => 0,
                'method' => $data['method'],
                'payment_date' => $data['payment_date'],
                'fee_start_date' => $data['fee_start_date'] ?? null,
                'fee_end_date' => $data['fee_end_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'received_by' => auth()->id(),
            ]);

            if ($payment->status === 'completed') {
                $this->membershipPayments->applyPackageToPayment(
                    $payment,
                    $member,
                    $plan,
                    $data['fee_start_date'] ?? null,
                    $data['fee_end_date'] ?? null,
                    $applyDiscount,
                );
                $this->adjustAccountBalance($payment->account_id, (float) $payment->amount);
            } else {
                $payment->update([
                    'fee_start_date' => $data['fee_start_date'] ?? null,
                    'fee_end_date' => $data['fee_end_date'] ?? null,
                ]);
            }
        });

        return redirect()->route('payments.index')->with('success', 'Fee payment recorded successfully.');
    }

    public function show(Payment $payment): View
    {
        $payment->load(['member', 'plan', 'account', 'receiver']);

        return view('payments.show', compact('payment'));
    }

    public function edit(Payment $payment): View
    {
        return view('payments.edit', array_merge(
            ['payment' => $payment],
            $this->formData()
        ));
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $data = $this->validated($request, editing: true);
        $wasCompleted = $payment->status === 'completed';
        $oldAmount = (float) $payment->amount;
        $oldAccountId = $payment->account_id;

        DB::transaction(function () use ($data, $payment, $wasCompleted, $oldAmount, $oldAccountId) {
            if ($wasCompleted) {
                $this->adjustAccountBalance($oldAccountId, -$oldAmount);
            }

            $payment->update([
                'member_id' => $data['member_id'],
                'account_id' => $data['account_id'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'payment_date' => $data['payment_date'],
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'invoice_id' => null,
            ]);

            if ($payment->status === 'completed') {
                $this->adjustAccountBalance($payment->account_id, (float) $payment->amount);
            }
        });

        return redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        DB::transaction(function () use ($payment) {
            if ($payment->status === 'completed') {
                $this->adjustAccountBalance($payment->account_id, -(float) $payment->amount);
            }
            $payment->delete();
        });

        return redirect()->route('payments.index')->with('success', 'Payment deleted successfully.');
    }

    private function formData(): array
    {
        return [
            'members' => Member::with(['trainer', 'activeSubscription.plan'])
                ->orderByDesc('joined_at')
                ->orderByDesc('id')
                ->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'plans' => MembershipPlan::where('is_active', true)->orderBy('price')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'membership_plan_id' => [$editing ? 'nullable' : 'required', 'exists:membership_plans,id'],
            'fee_start_date' => ['nullable', 'required_with:membership_plan_id', 'date'],
            'fee_end_date' => ['nullable', 'required_with:membership_plan_id', 'date', 'after_or_equal:fee_start_date'],
            'apply_package_discount' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank_transfer,online,other'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:completed,pending,failed,refunded'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function adjustAccountBalance(?int $accountId, float $delta): void
    {
        if (! $accountId || $delta == 0.0) {
            return;
        }

        $account = Account::find($accountId);
        if ($account) {
            $account->update([
                'current_balance' => (float) $account->current_balance + $delta,
            ]);
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = 'PAY-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Payment::where('payment_number', $number)->exists());

        return $number;
    }
}
