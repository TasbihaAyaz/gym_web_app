<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MemberCheckinPush extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload)
    {
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $payload = $this->payload;
        $member = $payload['member'] ?? [];
        $fee = $payload['fee'] ?? [];
        $name = $member['name'] ?? 'Member';
        $bioId = $member['bio_id'] ?? ($member['code'] ?? '');
        $headline = $payload['headline'] ?? 'Welcome';
        $subline = $payload['subline'] ?? 'Checked in successfully';
        $time = $payload['punched_at'] ?? '';
        $plan = $fee['plan'] ?? 'No package';
        $label = $fee['label'] ?? '';
        $pending = (float) ($fee['pending'] ?? 0);
        $icon = $member['avatar'] ?: ($payload['icon'] ?? null);

        $lines = array_values(array_filter([
            $subline,
            trim(($bioId !== '' ? 'Bio ID ' . $bioId : '') . ($time ? ($bioId !== '' ? ' · ' : '') . $time : '')),
            trim($plan . ($label ? ' · ' . $label : '')),
            $this->validityLine($fee),
            $pending > 0 ? ('Fee pending ' . $this->money($pending)) : null,
            $this->feeNote($fee, $pending),
        ]));

        $message = (new WebPushMessage)
            ->title($headline . ' · ' . $name)
            ->body(implode("\n", $lines))
            ->tag('checkin-' . ($payload['id'] ?? uniqid()))
            ->renotify(true)
            ->requireInteraction(true)
            ->vibrate([120, 60, 120])
            ->data([
                'url' => $payload['url'] ?? url('/'),
                'punch' => $payload,
                'sound' => $payload['sound'] ?? null,
            ])
            ->options([
                'TTL' => 120,
                'urgency' => 'high',
            ]);

        if ($icon) {
            $message->icon($icon)->badge($icon);
        }

        if (! empty($payload['icon']) && $payload['icon'] !== $icon) {
            $message->badge($payload['icon']);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $fee
     */
    private function validityLine(array $fee): ?string
    {
        $start = $fee['start'] ?? null;
        $end = $fee['end'] ?? null;

        if (! $start || ! $end) {
            return null;
        }

        return 'Valid ' . $start . ' → ' . $end;
    }

    /**
     * @param  array<string, mixed>  $fee
     */
    private function feeNote(array $fee, float $pending): ?string
    {
        $status = $fee['status'] ?? 'none';

        if ($status === 'expired') {
            $days = abs((int) ($fee['days_left'] ?? 0));

            return $days <= 1
                ? 'Fee expired — renewal pending.'
                : 'Fee expired ' . $days . ' days ago — pending renewal.';
        }

        if ($status === 'expiring') {
            $days = (int) ($fee['days_left'] ?? 0);

            return $days === 0
                ? 'Fee expires today — please renew.'
                : 'Fee expires in ' . $days . ' day' . ($days === 1 ? '' : 's') . '.';
        }

        if ($pending > 0) {
            return 'Outstanding balance ' . $this->money($pending) . '.';
        }

        return null;
    }

    private function money(float $amount): string
    {
        return 'Rs ' . number_format($amount, 0);
    }
}
