<?php

namespace App\Services;

use App\Models\Member;
use App\Models\ZktecoDevice;
use Illuminate\Support\Facades\Log;
use Rats\Zkteco\Lib\Helper\Util;
use Rats\Zkteco\Lib\ZKTeco;
use Throwable;

class ZktecoUserSyncService
{
    /**
     * Create / update member on the connected K50 (User ID + name).
     * Fingerprint is enrolled later on the device via Edit User.
     *
     * @return array{ok:bool,message:string}
     */
    public function pushMember(Member $member, ?ZktecoDevice $device = null): array
    {
        $pin = trim((string) $member->device_user_id);
        if ($pin === '' || ! ctype_digit($pin)) {
            return ['ok' => false, 'message' => 'Member has no numeric biometric ID.'];
        }

        $uid = (int) $pin;
        if ($uid < 1 || $uid > Util::USHRT_MAX) {
            return ['ok' => false, 'message' => 'Biometric ID out of device range.'];
        }

        if (! function_exists('socket_create')) {
            return ['ok' => false, 'message' => 'PHP sockets extension is disabled.'];
        }

        $device = $device ?: ZktecoDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->latest('last_seen_at')
            ->first();

        if (! $device?->ip_address) {
            return ['ok' => false, 'message' => 'No biometric device connected. Connect K50 first.'];
        }

        $name = $this->deviceName($member);
        $zk = new ZKTeco($device->ip_address, (int) ($device->port ?: 4370));

        try {
            if (! $zk->connect()) {
                return ['ok' => false, 'message' => 'Could not reach biometric device at ' . $device->ip_address];
            }

            // Pause device while writing user record
            try {
                $zk->disableDevice();
            } catch (Throwable) {
            }

            $result = $zk->setUser($uid, (string) $uid, $name, '', Util::LEVEL_USER, 0);

            try {
                $zk->enableDevice();
            } catch (Throwable) {
            }

            $zk->disconnect();

            $device->update(['last_seen_at' => now()]);

            if ($result === false) {
                return ['ok' => false, 'message' => 'Device rejected user write for ID ' . $uid];
            }

            return [
                'ok' => true,
                'message' => "Saved on K50 as ID {$uid} ({$name}). Open All Members → find {$uid} → Edit → enroll thumb.",
            ];
        } catch (Throwable $e) {
            try {
                $zk->disconnect();
            } catch (Throwable) {
            }

            Log::error('ZKTeco push member failed', [
                'member_id' => $member->id,
                'bio_id' => $uid,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Device error: ' . $e->getMessage()];
        }
    }

    private function deviceName(Member $member): string
    {
        $name = trim($member->full_name);
        // Device name field max 24 bytes
        $name = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '', $name) ?: ('User ' . $member->device_user_id);
        if (strlen($name) > 24) {
            $name = substr($name, 0, 24);
        }

        return $name;
    }
}
