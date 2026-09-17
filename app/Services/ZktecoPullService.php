<?php

namespace App\Services;

use App\Models\ZktecoDevice;
use App\Models\ZktecoPunch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Rats\Zkteco\Lib\ZKTeco;
use Throwable;

class ZktecoPullService
{
    public function __construct(private ZktecoAdmsService $adms)
    {
    }

    /**
     * Connect to device by IP, register/update it, optionally sync attendance.
     *
     * @return array{ok:bool,message:string,device:?ZktecoDevice,users:?int,attendance_total:?int,imported:?int}
     */
    public function connect(string $ip, int $port = 4370, bool $sync = true, int $syncDays = 2): array
    {
        $ip = trim($ip);
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->fail('Invalid device IP address.');
        }

        if (! function_exists('socket_create')) {
            return $this->fail('PHP sockets extension is disabled. Enable extension=sockets in php.ini and restart Apache.');
        }

        $lockKey = 'zkteco-sync-' . $ip;
        if (! Cache::add($lockKey, 1, 90)) {
            return $this->fail('Device sync already running. Try again in a moment.');
        }

        $zk = new ZKTeco($ip, $port);

        try {
            if (! $zk->connect()) {
                return $this->fail("Could not connect to {$ip}:{$port}. Check device power, LAN, and Comm settings.");
            }

            $meta = $this->readMeta($zk);
            $users = [];
            try {
                $users = $zk->getUser() ?: [];
            } catch (Throwable $e) {
                Log::warning('ZKTeco getUser failed', ['error' => $e->getMessage()]);
            }

            $attendance = [];
            if ($sync) {
                try {
                    $attendance = $zk->getAttendance() ?: [];
                } catch (Throwable $e) {
                    Log::warning('ZKTeco getAttendance failed', ['error' => $e->getMessage()]);
                }
            }

            $device = $this->persistDevice($ip, $port, $meta);

            $imported = 0;
            if ($sync && is_array($attendance)) {
                $imported = $this->importAttendance($device, $attendance, $syncDays);
                // Clear device logs after import so the next live sync is near-instant
                try {
                    $zk->clearAttendance();
                } catch (Throwable $e) {
                    Log::warning('ZKTeco clearAttendance failed', ['error' => $e->getMessage()]);
                }
                $device->update(['last_synced_at' => now(), 'last_seen_at' => now()]);
            }

            $zk->disconnect();

            return [
                'ok' => true,
                'message' => "Connected to {$meta['model']} ({$meta['serial']}). Users: " . count($users) . '. Imported punches: ' . $imported . '.',
                'device' => $device->fresh(),
                'users' => count($users),
                'attendance_total' => is_array($attendance) ? count($attendance) : 0,
                'imported' => $imported,
            ];
        } catch (Throwable $e) {
            try {
                $zk->disconnect();
            } catch (Throwable) {
            }
            Log::error('ZKTeco pull connect failed', ['ip' => $ip, 'error' => $e->getMessage()]);

            return $this->fail('Connection error: ' . $e->getMessage());
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Fast live sync for dashboard: pull new punches only, then clear device buffer.
     *
     * @return array{ok:bool,message:string,imported:int,ms:int}
     */
    public function liveSync(?ZktecoDevice $device = null): array
    {
        $started = (int) (microtime(true) * 1000);
        $device = $device ?: ZktecoDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->latest('last_seen_at')
            ->first();

        if (! $device?->ip_address) {
            return ['ok' => false, 'busy' => false, 'message' => 'No connected device.', 'imported' => 0, 'ms' => 0];
        }

        if (! function_exists('socket_create')) {
            return ['ok' => false, 'busy' => false, 'message' => 'Sockets disabled.', 'imported' => 0, 'ms' => 0];
        }

        $lockKey = 'zkteco-sync-' . $device->ip_address;
        if (! Cache::add($lockKey, 1, 45)) {
            return ['ok' => false, 'busy' => true, 'message' => 'Sync in progress.', 'imported' => 0, 'ms' => 0];
        }

        $zk = new ZKTeco($device->ip_address, (int) ($device->port ?: 4370));
        $this->setSocketTimeout($zk, 3);

        try {
            if (! $zk->connect()) {
                return ['ok' => false, 'busy' => false, 'message' => 'Device unreachable.', 'imported' => 0, 'ms' => (int) (microtime(true) * 1000) - $started];
            }

            $attendance = $zk->getAttendance() ?: [];
            // Import anything newer than last stored punch (fallback: last 1 day)
            $imported = $this->importSinceLastPunch($device, is_array($attendance) ? $attendance : []);

            try {
                $zk->clearAttendance();
            } catch (Throwable $e) {
                Log::warning('ZKTeco clearAttendance failed', ['error' => $e->getMessage()]);
            }

            $device->update([
                'last_seen_at' => now(),
                'last_synced_at' => now(),
            ]);

            $zk->disconnect();

            return [
                'ok' => true,
                'busy' => false,
                'message' => 'Live sync ok',
                'imported' => $imported,
                'ms' => (int) (microtime(true) * 1000) - $started,
            ];
        } catch (Throwable $e) {
            try {
                $zk->disconnect();
            } catch (Throwable) {
            }

            return [
                'ok' => false,
                'busy' => false,
                'message' => $e->getMessage(),
                'imported' => 0,
                'ms' => (int) (microtime(true) * 1000) - $started,
            ];
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Lightweight real reachability check (connect + device name).
     *
     * @return array{ok:bool,connected:bool,message:string,ip:?string,ms:int}
     */
    public function probe(?ZktecoDevice $device = null): array
    {
        $started = (int) (microtime(true) * 1000);
        $device = $device ?: ZktecoDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->latest('last_seen_at')
            ->first();

        if (! $device?->ip_address) {
            return [
                'ok' => false,
                'connected' => false,
                'message' => 'No biometric device',
                'ip' => null,
                'ms' => 0,
            ];
        }

        if (! function_exists('socket_create')) {
            return [
                'ok' => false,
                'connected' => false,
                'message' => 'Sockets disabled',
                'ip' => $device->ip_address,
                'ms' => 0,
            ];
        }

        $zk = new ZKTeco($device->ip_address, (int) ($device->port ?: 4370));
        $this->setSocketTimeout($zk, 2);

        try {
            if (! $zk->connect()) {
                return [
                    'ok' => false,
                    'connected' => false,
                    'message' => 'Device unreachable',
                    'ip' => $device->ip_address,
                    'ms' => (int) (microtime(true) * 1000) - $started,
                ];
            }

            // Confirm protocol handshake with a tiny read
            try {
                $zk->deviceName();
            } catch (Throwable) {
            }

            $device->update(['last_seen_at' => now()]);
            $zk->disconnect();

            return [
                'ok' => true,
                'connected' => true,
                'message' => 'K50 online',
                'ip' => $device->ip_address,
                'ms' => (int) (microtime(true) * 1000) - $started,
            ];
        } catch (Throwable $e) {
            try {
                $zk->disconnect();
            } catch (Throwable) {
            }

            return [
                'ok' => false,
                'connected' => false,
                'message' => 'Device unreachable',
                'ip' => $device->ip_address,
                'ms' => (int) (microtime(true) * 1000) - $started,
            ];
        }
    }

    private function setSocketTimeout(ZKTeco $zk, int $seconds = 2): void
    {
        if (! isset($zk->_zkclient) || ! is_resource($zk->_zkclient) && ! ($zk->_zkclient instanceof \Socket)) {
            return;
        }

        $timeout = ['sec' => max(1, $seconds), 'usec' => 0];
        @socket_set_option($zk->_zkclient, SOL_SOCKET, SO_RCVTIMEO, $timeout);
        @socket_set_option($zk->_zkclient, SOL_SOCKET, SO_SNDTIMEO, $timeout);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importAttendance(ZktecoDevice $device, array $rows, int $syncDays = 2): int
    {
        $since = now()->subDays(max(1, $syncDays))->startOfDay();

        return $this->importRows($device, $rows, $since);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importSinceLastPunch(ZktecoDevice $device, array $rows): int
    {
        $last = ZktecoPunch::query()
            ->where('serial_number', $device->serial_number)
            ->max('punched_at');

        $since = $last
            ? Carbon::parse($last)->subMinute()
            : now()->subDay()->startOfDay();

        return $this->importRows($device, $rows, $since);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importRows(ZktecoDevice $device, array $rows, Carbon $since): int
    {
        $lines = [];

        foreach ($rows as $row) {
            $pin = (string) ($row['id'] ?? $row['uid'] ?? '');
            $timestamp = (string) ($row['timestamp'] ?? '');
            if ($pin === '' || $timestamp === '') {
                continue;
            }

            try {
                $at = Carbon::parse($timestamp);
            } catch (Throwable) {
                continue;
            }

            if ($at->lt($since)) {
                continue;
            }

            $status = (int) ($row['type'] ?? $row['state'] ?? 0);
            $verify = (int) ($row['state'] ?? 1);
            $lines[] = $pin . "\t" . $at->format('Y-m-d H:i:s') . "\t" . $status . "\t" . $verify;
        }

        if ($lines === []) {
            return 0;
        }

        $imported = 0;
        foreach (array_chunk($lines, 250) as $chunk) {
            $imported += $this->adms->ingestAttLog($device->serial_number, implode("\n", $chunk));
        }

        return $imported;
    }

    /**
     * @return array{serial:string,model:string,firmware:?string}
     */
    private function readMeta(ZKTeco $zk): array
    {
        $serialRaw = (string) $zk->serialNumber();
        $nameRaw = (string) $zk->deviceName();
        $versionRaw = (string) $zk->version();

        return [
            'serial' => $this->cleanOption($serialRaw, 'SerialNumber') ?: ('ZK-' . uniqid()),
            'model' => $this->cleanOption($nameRaw, 'DeviceName') ?: 'K50',
            'firmware' => ($v = trim(str_replace("\0", '', $versionRaw))) !== '' ? mb_substr($v, 0, 80) : null,
        ];
    }

    /**
     * @param  array{serial:string,model:string,firmware:?string}  $meta
     */
    private function persistDevice(string $ip, int $port, array $meta): ZktecoDevice
    {
        ZktecoDevice::query()
            ->where('ip_address', $ip)
            ->where('serial_number', '!=', $meta['serial'])
            ->delete();

        return ZktecoDevice::query()->updateOrCreate(
            ['serial_number' => $meta['serial']],
            [
                'name' => 'ZKTeco ' . $meta['model'],
                'model' => $meta['model'],
                'firmware' => $meta['firmware'],
                'ip_address' => $ip,
                'port' => $port,
                'last_seen_at' => now(),
                'is_active' => true,
            ]
        );
    }

    private function cleanOption(string $raw, string $key): string
    {
        $raw = trim(str_replace("\0", '', $raw));
        if (str_contains($raw, $key . '=')) {
            $raw = (string) preg_replace('/^.*' . preg_quote($key, '/') . '=/', '', $raw);
        }

        return trim($raw);
    }

    /**
     * @return array{ok:bool,message:string,device:?ZktecoDevice,users:?int,attendance_total:?int,imported:?int}
     */
    private function fail(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'device' => null,
            'users' => null,
            'attendance_total' => null,
            'imported' => null,
        ];
    }
}
