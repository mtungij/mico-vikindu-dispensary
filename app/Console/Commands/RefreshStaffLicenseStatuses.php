<?php

namespace App\Console\Commands;

use App\Services\LicenseStatusService;
use Illuminate\Console\Command;

class RefreshStaffLicenseStatuses extends Command
{
    protected $signature = 'staff:refresh-license-statuses';

    protected $description = 'Refresh staff professional license statuses based on expiry dates.';

    public function handle(LicenseStatusService $service): int
    {
        $count = $service->refreshAll();
        $this->info("Refreshed {$count} staff license status records.");

        return self::SUCCESS;
    }
}
