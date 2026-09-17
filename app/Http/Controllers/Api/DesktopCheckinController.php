<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ZktecoDeviceController;
use App\Models\ZktecoPunch;
use App\Services\CheckinAlertService;
use App\Services\ZktecoPullService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopCheckinController extends Controller
{
    /**
     * Same payload as the web live toast poll, with absolute media URLs for desktop.
     */
    public function poll(Request $request, CheckinAlertService $alerts): JsonResponse
    {
        $response = app(ZktecoDeviceController::class)->pollCheckin($request, $alerts);
        $data = $response->getData(true);

        return response()->json($this->enrichPollPayload($data, $request, $alerts));
    }

    /**
     * Pull from K50, then return any new check-in payloads for immediate popup.
     */
    public function liveSync(Request $request, ZktecoPullService $pull, CheckinAlertService $alerts): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);

        $sync = app(ZktecoDeviceController::class)->liveSync($pull)->getData(true);

        // Always return punches newer than the desktop cursor (even if another
        // client already imported them from the device).
        $rows = ZktecoPunch::query()
            ->with(['member.activeSubscription.plan'])
            ->where('id', '>', $afterId)
            ->whereNotNull('member_id')
            ->whereIn('applied_as', ['check_in', 'ignored'])
            ->orderBy('id')
            ->limit(40)
            ->get();

        $punches = [];
        foreach ($rows as $punch) {
            $payload = $alerts->payload($punch, $alerts->pendingBalance($punch->member));
            if ($payload) {
                $punches[] = $this->absolutizePunch($payload);
            }
        }

        $latestId = (int) ZktecoPunch::query()
            ->whereNotNull('member_id')
            ->whereIn('applied_as', ['check_in', 'ignored'])
            ->max('id');

        return response()->json([
            'ok' => (bool) ($sync['ok'] ?? false),
            'busy' => (bool) ($sync['busy'] ?? false),
            'connected' => (bool) ($sync['connected'] ?? false),
            'status_label' => $sync['status_label'] ?? null,
            'message' => $sync['message'] ?? null,
            'imported' => (int) ($sync['imported'] ?? 0),
            'ms' => (int) ($sync['ms'] ?? 0),
            'punches' => $punches,
            'punch' => $punches[count($punches) - 1] ?? null,
            'after_id' => $rows->isNotEmpty() ? (int) $rows->last()->id : $afterId,
            'last_id' => $latestId,
            'sounds' => [
                'checkin' => voice_asset('fit-gen.mp3'),
                'expired' => voice_asset('fee-expired.mp3'),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichPollPayload(array $data, Request $request, CheckinAlertService $alerts): array
    {
        if (! empty($data['punch']) && is_array($data['punch'])) {
            $data['punch'] = $this->absolutizePunch($data['punch']);
        }

        if (! empty($data['punches']) && is_array($data['punches'])) {
            $data['punches'] = array_map(fn ($p) => $this->absolutizePunch($p), $data['punches']);
        }

        $data['sounds'] = [
            'checkin' => voice_asset('fit-gen.mp3'),
            'expired' => voice_asset('fee-expired.mp3'),
        ];

        if ($request->boolean('bootstrap')) {
            $latest = ZktecoPunch::query()
                ->with(['member.activeSubscription.plan'])
                ->whereNotNull('member_id')
                ->whereIn('applied_as', ['check_in', 'ignored'])
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $payload = $alerts->payload($latest, $alerts->pendingBalance($latest->member));
                $data['latest_punch'] = $payload ? $this->absolutizePunch($payload) : null;
            } else {
                $data['latest_punch'] = null;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $punch
     * @return array<string, mixed>
     */
    private function absolutizePunch(array $punch): array
    {
        foreach (['sound', 'icon', 'url'] as $key) {
            if (! empty($punch[$key]) && is_string($punch[$key])) {
                $punch[$key] = $this->absoluteUrl($punch[$key]);
            }
        }

        if (! empty($punch['member']['avatar']) && is_string($punch['member']['avatar'])) {
            $punch['member']['avatar'] = $this->absoluteUrl($punch['member']['avatar']);
        }

        return $punch;
    }

    private function absoluteUrl(string $value): string
    {
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return url($value);
    }
}
