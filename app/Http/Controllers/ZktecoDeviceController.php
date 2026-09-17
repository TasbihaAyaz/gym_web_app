<?php

namespace App\Http\Controllers;

use App\Models\ZktecoDevice;
use App\Models\ZktecoPunch;
use App\Services\CheckinAlertService;
use App\Services\ZktecoPullService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZktecoDeviceController extends Controller
{
    public function index(Request $request): View
    {
        $devices = ZktecoDevice::query()->latest('last_seen_at')->get();
        $punches = ZktecoPunch::query()
            ->with('member')
            ->latest('punched_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'devices' => $devices->count(),
            'online' => $devices->filter(fn (ZktecoDevice $d) => $d->isOnline())->count(),
            'today' => ZktecoPunch::whereDate('punched_at', now()->toDateString())->count(),
            'unmatched' => ZktecoPunch::where('applied_as', 'unmatched')->count(),
        ];

        $serverUrl = rtrim(config('app.url'), '/');
        $admsPath = $serverUrl . '/iclock/cdata';
        $pcLanIp = '192.168.100.24';
        $defaultDeviceIp = old('ip', $devices->firstWhere('ip_address', '!=', null)?->ip_address ?? '192.168.100.205');

        return view('zkteco.index', compact('devices', 'punches', 'stats', 'serverUrl', 'admsPath', 'pcLanIp', 'defaultDeviceIp'));
    }

    public function connect(Request $request, ZktecoPullService $pull): RedirectResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'sync_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        set_time_limit(180);

        $result = $pull->connect(
            $data['ip'],
            (int) ($data['port'] ?? 4370),
            true,
            (int) ($data['sync_days'] ?? 2)
        );

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('zkteco.index')->with('success', $result['message']);
    }

    public function sync(Request $request, ZktecoDevice $device, ZktecoPullService $pull): RedirectResponse
    {
        if (! $device->ip_address) {
            return back()->with('error', 'This device has no IP address saved.');
        }

        set_time_limit(180);

        $days = (int) $request->input('sync_days', 2);
        $result = $pull->connect($device->ip_address, (int) ($device->port ?: 4370), true, $days);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    public function liveSync(ZktecoPullService $pull): JsonResponse
    {
        set_time_limit(60);
        $result = $pull->liveSync();

        return response()->json($this->withConnectionStatus($result));
    }

    public function deviceStatus(ZktecoPullService $pull): JsonResponse
    {
        set_time_limit(15);
        $probe = $pull->probe();

        $connected = (bool) ($probe['connected'] ?? false);
        $ip = $probe['ip'] ?? null;

        return response()->json([
            'ok' => $probe['ok'] ?? false,
            'connected' => $connected,
            'status_label' => $connected
                ? 'Biometric connected'
                : ($ip ? 'Biometric offline' : 'No biometric device'),
            'status_title' => $connected
                ? ('K50 online' . ($ip ? ' · ' . $ip : ''))
                : ($ip
                    ? ('Unreachable · ' . $ip)
                    : 'Connect a ZKTeco K50 under Biometric Device'),
            'ip' => $ip,
            'ms' => $probe['ms'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withConnectionStatus(array $result): array
    {
        $device = ZktecoDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->latest('last_seen_at')
            ->first();

        // Only mark connected when we actually reached the device this request
        $connected = false;
        if (! empty($result['busy'])) {
            // Don't flip status while another sync holds the lock
            $connected = $device?->isConnected(20) ?? false;
        } elseif (! empty($result['ok']) && ($result['message'] ?? '') === 'Live sync ok') {
            $connected = true;
        }

        if (! $device) {
            $connected = false;
        }

        $result['connected'] = $connected;
        $result['status_label'] = $connected
            ? 'Biometric connected'
            : ($device ? 'Biometric offline' : 'No biometric device');
        $result['status_title'] = $connected
            ? ('K50 online' . ($device?->ip_address ? ' · ' . $device->ip_address : ''))
            : ($device
                ? ('Unreachable · ' . $device->ip_address)
                : 'Connect a ZKTeco K50 under Biometric Device');

        return $result;
    }

    public function welcome(): View
    {
        return view('zkteco.welcome', [
            'gymName' => 'Fit Generation',
            'pollUrl' => route('zkteco.poll-checkin'),
            'displaySeconds' => 10,
        ]);
    }

    public function pollCheckin(Request $request, CheckinAlertService $alerts): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);
        $bootstrap = $request->boolean('bootstrap');
        $limit = min(50, max(1, (int) $request->query('limit', 40)));

        $latestId = (int) ZktecoPunch::query()
            ->whereNotNull('member_id')
            ->whereIn('applied_as', ['check_in', 'ignored'])
            ->max('id');

        if ($bootstrap) {
            return response()->json([
                'punch' => null,
                'punches' => [],
                'last_id' => $latestId,
                'after_id' => $latestId,
                'server_time' => now()->toIso8601String(),
            ]);
        }

        $rows = ZktecoPunch::query()
            ->with(['member.activeSubscription.plan'])
            ->where('id', '>', $afterId)
            ->whereNotNull('member_id')
            ->whereIn('applied_as', ['check_in', 'ignored'])
            // Allow delayed live-sync imports (not only last 5 minutes)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $pendingByMember = [];
        $memberIds = $rows->pluck('member_id')->filter()->unique()->values();
        if ($memberIds->isNotEmpty()) {
            $members = \App\Models\Member::query()
                ->with(['activeSubscription.plan'])
                ->whereIn('id', $memberIds)
                ->get()
                ->keyBy('id');

            $today = now()->toDateString();
            foreach ($members as $id => $member) {
                $sub = $member->activeSubscription;
                $balanceDue = round((float) ($sub?->balance_due ?? 0), 2);
                if ($balanceDue > 0) {
                    $pendingByMember[$id] = $balanceDue;
                } elseif ($sub?->end_date && $sub->end_date->toDateString() < $today) {
                    $pendingByMember[$id] = round((float) ($sub->plan?->price ?? $sub->amount_paid ?? 0), 2);
                }
            }
        }

        $punches = [];
        foreach ($rows as $punch) {
            $payload = $alerts->payload(
                $punch,
                (float) ($pendingByMember[$punch->member_id] ?? 0)
            );
            if ($payload) {
                $punches[] = $payload;
            }
        }

        $batchAfter = $afterId;
        if ($rows->isNotEmpty()) {
            $batchAfter = (int) $rows->last()->id;
        }

        return response()->json([
            // Keep single punch for older clients; prefer newest in batch
            'punch' => $punches[count($punches) - 1] ?? null,
            'punches' => $punches,
            'last_id' => $latestId,
            'after_id' => $batchAfter,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function update(Request $request, ZktecoDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $device->update([
            'name' => $data['name'] ?? $device->name,
            'model' => $data['model'] ?? $device->model,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Device updated.');
    }

    public function destroy(ZktecoDevice $device): RedirectResponse
    {
        $device->delete();

        return back()->with('success', 'Device removed.');
    }
}
