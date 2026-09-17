<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\ZktecoDevice;
use App\Models\ZktecoPunch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZktecoAdmsService
{
    public function touchDevice(string $serial, ?string $ip = null): ZktecoDevice
    {
        $device = ZktecoDevice::query()->firstOrCreate(
            ['serial_number' => $serial],
            [
                'name' => 'ZKTeco K50',
                'model' => 'K50',
                'is_active' => true,
            ]
        );

        $device->forceFill([
            'last_seen_at' => now(),
            'ip_address' => $ip ?: $device->ip_address,
        ])->save();

        return $device;
    }

    public function handshakeOptions(ZktecoDevice $device): string
    {
        return implode("\r\n", [
            'GET OPTION FROM: ' . $device->serial_number,
            'Stamp=' . (int) $device->att_log_stamp,
            'OpStamp=' . (int) $device->oper_log_stamp,
            'ErrorDelay=60',
            'Delay=30',
            'ResLogDay=18250',
            'ResLogDelCount=10000',
            'ResLogCount=50000',
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransFlag=1111000000',
            'Realtime=1',
            'Encrypt=0',
        ]);
    }

    /**
     * Parse ATTLOG body and apply punches to gym attendance.
     *
     * @return int Number of punch lines accepted
     */
    public function ingestAttLog(string $serial, string $body, ?int $stamp = null): int
    {
        $device = $this->touchDevice($serial);
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
        $accepted = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($this->storePunchLine($device, $line)) {
                $accepted++;
            }
        }

        if ($stamp !== null) {
            $device->update(['att_log_stamp' => max($device->att_log_stamp, $stamp)]);
        }

        return $accepted;
    }

    public function ingestOperLog(string $serial, string $body, ?int $stamp = null): int
    {
        $device = $this->touchDevice($serial);
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
        $count = 0;

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $count++;
            }
        }

        if ($stamp !== null) {
            $device->update(['oper_log_stamp' => max($device->oper_log_stamp, $stamp)]);
        }

        return $count;
    }

    private function storePunchLine(ZktecoDevice $device, string $line): bool
    {
        // Common formats:
        // PIN\tYYYY-MM-DD HH:MM:SS\tSTATUS\tVERIFY\t...
        // PIN YYYY-MM-DD HH:MM:SS STATUS VERIFY ...
        $parts = str_contains($line, "\t")
            ? explode("\t", $line)
            : preg_split('/\s+/', $line, 7);

        if (! is_array($parts) || count($parts) < 2) {
            return false;
        }

        $pin = trim((string) $parts[0]);
        $timeRaw = trim((string) ($parts[1] ?? ''));

        // Space-separated may split date/time into two tokens
        if (! str_contains($line, "\t") && isset($parts[2]) && preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) $parts[2])) {
            $timeRaw = trim($parts[1] . ' ' . $parts[2]);
            $status = isset($parts[3]) ? (int) $parts[3] : null;
            $verify = isset($parts[4]) ? (int) $parts[4] : null;
        } else {
            $status = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : null;
            $verify = isset($parts[3]) && $parts[3] !== '' ? (int) $parts[3] : null;
        }

        if ($pin === '' || $timeRaw === '') {
            return false;
        }

        try {
            $punchedAt = Carbon::parse($timeRaw);
        } catch (\Throwable $e) {
            Log::warning('ZKTeco: invalid punch time', ['line' => $line, 'error' => $e->getMessage()]);

            return false;
        }

        $exists = ZktecoPunch::query()
            ->where('serial_number', $device->serial_number)
            ->where('device_user_id', $pin)
            ->where('punched_at', $punchedAt->format('Y-m-d H:i:s'))
            ->exists();

        if ($exists) {
            return true; // already stored — still OK for device
        }

        $punch = null;

        DB::transaction(function () use ($device, $pin, $punchedAt, $status, $verify, $line, &$punch) {
            $member = $this->resolveMember($pin);
            $appliedAs = 'unmatched';

            if ($member) {
                $appliedAs = $this->applyToAttendance($member, $punchedAt);
            }

            $punch = ZktecoPunch::create([
                'serial_number' => $device->serial_number,
                'device_user_id' => $pin,
                'member_id' => $member?->id,
                'punched_at' => $punchedAt,
                'status' => $status,
                'verify_type' => $verify,
                'raw_line' => mb_substr($line, 0, 500),
                'applied_as' => $appliedAs,
            ]);
        });

        if ($punch) {
            $this->queueCheckinPush($punch);
        }

        return true;
    }

    public function resolveMember(string $pin): ?Member
    {
        $pin = trim($pin);

        $member = Member::query()->where('device_user_id', $pin)->first();
        if ($member) {
            return $member;
        }

        // Allow bare numeric match against MEM-00012 style codes
        $digits = ltrim($pin, '0');
        if ($digits !== '' && ctype_digit($pin)) {
            $padded = str_pad($digits, 5, '0', STR_PAD_LEFT);
            $member = Member::query()
                ->where(function ($q) use ($padded, $digits) {
                    $q->where('member_code', 'MEM-' . $padded)
                        ->orWhere('member_code', 'MEM-' . $digits);
                })
                ->first();
            if ($member) {
                return $member;
            }
        }

        return Member::query()->where('member_code', $pin)->first();
    }

    /**
     * Check-in only (no checkout). Gym day runs until 1:00 AM —
     * punches from midnight–00:59 belong to the previous calendar date.
     */
    private function applyToAttendance(Member $member, Carbon $punchedAt): string
    {
        $date = $this->gymAttendanceDate($punchedAt);
        $time = $punchedAt->format('H:i:s');

        $attendance = Attendance::query()
            ->where('member_id', $member->id)
            ->whereDate('attendance_date', $date)
            ->lockForUpdate()
            ->first();

        if (! $attendance) {
            Attendance::create([
                'member_id' => $member->id,
                'attendance_date' => $date,
                'check_in' => $time,
                'check_out' => null,
                'method' => 'biometric',
                'notes' => 'ZKTeco K50 auto check-in',
            ]);

            return 'check_in';
        }

        if (! $attendance->check_in) {
            $attendance->update([
                'check_in' => $time,
                'check_out' => null,
                'method' => 'biometric',
                'notes' => trim(($attendance->notes ? $attendance->notes . ' | ' : '') . 'ZKTeco check-in'),
            ]);

            return 'check_in';
        }

        // Already checked in for this gym day — ignore (do not record checkout)
        return 'ignored';
    }

    /**
     * Gym closes at 01:00. Before 1 AM counts as the previous day's attendance.
     */
    private function gymAttendanceDate(Carbon $punchedAt): string
    {
        $closeHour = 1; // 1 AM

        if ((int) $punchedAt->format('G') < $closeHour) {
            return $punchedAt->copy()->subDay()->toDateString();
        }

        return $punchedAt->toDateString();
    }

    private function queueCheckinPush(ZktecoPunch $punch): void
    {
        $alerts = app(CheckinAlertService::class);

        if (! $alerts->shouldNotify($punch)) {
            return;
        }

        $punchId = (int) $punch->id;

        dispatch(function () use ($punchId) {
            app(CheckinAlertService::class)->notifyStaff($punchId);
        })->afterResponse();
    }
}
