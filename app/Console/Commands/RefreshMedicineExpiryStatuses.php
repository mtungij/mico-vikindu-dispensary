<?php

namespace App\Console\Commands;

use App\Services\MedicineExpiryService;
use Illuminate\Console\Command;

class RefreshMedicineExpiryStatuses extends Command
{
    protected $signature = 'pharmacy:refresh-expiry-statuses';
    protected $description = 'Refresh expired medicine batch statuses.';

    public function handle(MedicineExpiryService $expiry): int
    {
        $updated = $expiry->refreshBatchStatuses();
        $this->info("Updated {$updated} expired batch statuses.");
        return self::SUCCESS;
    }
}
