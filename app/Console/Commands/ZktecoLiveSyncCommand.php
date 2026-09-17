<?php

namespace App\Console\Commands;

use App\Models\ZktecoDevice;
use App\Services\ZktecoPullService;
use Illuminate\Console\Command;

class ZktecoLiveSyncCommand extends Command
{
    protected $signature = 'zkteco:live-sync';

    protected $description = 'Pull latest punches from the connected ZKTeco K50';

    public function handle(ZktecoPullService $pull): int
    {
        $device = ZktecoDevice::query()
            ->where('is_active', true)
            ->whereNotNull('ip_address')
            ->latest('last_seen_at')
            ->first();

        if (! $device) {
            $this->warn('No active ZKTeco device configured.');

            return self::SUCCESS;
        }

        $result = $pull->liveSync($device);
        $this->info(($result['ok'] ? 'OK' : 'FAIL') . ' imported=' . $result['imported'] . ' ms=' . $result['ms'] . ' ' . $result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
