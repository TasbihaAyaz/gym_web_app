<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Models\ZktecoPunch;
use App\Notifications\MemberCheckinPush;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CheckinAlertService
{
    /**
     * @param  array<int, float>  $pendingByMember
     * @return array<string, mixed>|null
     */
    public function payload(ZktecoPunch $punch, float $pendingBalance = 0.0): ?array
    {
        $member = $punch->member;
        if (! $member) {
            return null;
        }

        $sub = $member->activeSubscription;
        $today = now()->startOfDay();
        $feeStatus = 'none';
        $daysLeft = null;

        if ($sub?->end_date) {
            $end = $sub->end_date->copy()->startOfDay();
            if ($end->lt($today)) {
                $feeStatus = 'expired';
                $daysLeft = (int) (-1 * $end->diffInDays($today));
            } elseif ($end->lte($today->copy()->addDays(7))) {
                $feeStatus = 'expiring';
                $daysLeft = (int) $today->diffInDays($end);
            } else {
                $feeStatus = 'active';
                $daysLeft = (int) $today->diffInDays($end);
            }
        }

        $isCheckIn = $punch->applied_as === 'check_in';

        $feeLabel = match ($feeStatus) {
            'active' => $pendingBalance > 0 ? 'Fee Active · Balance Due' : 'Fee Active',
            'expiring' => 'Expiring Soon',
            'expired' => 'Fee Pending',
            default => 'No Fee Period',
        };

        $soundPath = $feeStatus === 'expired'
            ? voice_asset('fee-expired.mp3')
            : voice_asset('fit-gen.mp3');

        return [
            'id' => $punch->id,
            'type' => 'check_in',
            'applied_as' => $punch->applied_as,
            'play_sound' => true,
            'headline' => 'Welcome',
            'subline' => $isCheckIn
                ? 'Checked in successfully'
                : 'Already checked in today',
            'punched_at' => $punch->punched_at?->format('h:i A'),
            'punched_date' => $punch->punched_at?->format('D, M j'),
            'url' => route('members.show', $member),
            'icon' => asset('assets/img/checkin-icon.png'),
            'sound' => $soundPath ?: null,
            'member' => [
                'id' => $member->id,
                'name' => $member->full_name,
                'code' => $member->member_code,
                'bio_id' => $member->device_user_id ?: '',
                'avatar' => $member->avatar_url,
                'initials' => $member->initials,
                'status' => $member->status,
            ],
            'fee' => [
                'status' => $feeStatus,
                'plan' => $sub?->plan?->name,
                'plan_price' => $sub?->plan ? (float) $sub->plan->price : null,
                'amount_paid' => $sub ? (float) $sub->amount_paid : null,
                'pending' => round($pendingBalance, 2),
                'start' => $sub?->start_date?->format('M j, Y'),
                'end' => $sub?->end_date?->format('M j, Y'),
                'start_iso' => $sub?->start_date?->toDateString(),
                'end_iso' => $sub?->end_date?->toDateString(),
                'days_left' => $daysLeft,
                'label' => $feeLabel,
            ],
        ];
    }

    public function pendingBalance(Member $member): float
    {
        $sub = $member->activeSubscription;
        if (! $sub) {
            return 0.0;
        }

        $balanceDue = round((float) ($sub->balance_due ?? 0), 2);
        $today = now()->toDateString();

        if ($sub->end_date && $sub->end_date->toDateString() < $today) {
            if ($balanceDue > 0) {
                return $balanceDue;
            }

            return round((float) ($sub->plan?->price ?? $sub->amount_paid ?? 0), 2);
        }

        return $balanceDue;
    }

    public function shouldNotify(ZktecoPunch $punch): bool
    {
        if (! $punch->member_id) {
            return false;
        }

        if (! in_array($punch->applied_as, ['check_in', 'ignored'], true)) {
            return false;
        }

        $punchedAt = $punch->punched_at;

        return $punchedAt !== null && $punchedAt->gte(now()->subMinutes(15));
    }

    public function notifyStaff(int $punchId): void
    {
        if (! config('checkin.browser_alerts')) {
            return;
        }

        try {
            $punch = ZktecoPunch::query()
                ->with(['member.activeSubscription.plan'])
                ->find($punchId);

            if (! $punch || ! $this->shouldNotify($punch)) {
                return;
            }

            $payload = $this->payload($punch, $this->pendingBalance($punch->member));
            if (! $payload) {
                return;
            }

            $users = User::query()
                ->where('status', 'active')
                ->whereHas('pushSubscriptions')
                ->get();

            if ($users->isEmpty()) {
                return;
            }

            Notification::send($users, new MemberCheckinPush($payload));
        } catch (Throwable $e) {
            Log::warning('Check-in web push failed', [
                'punch_id' => $punchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
