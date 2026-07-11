<?php

namespace App\Console\Commands;

use App\Services\BedReservationService;
use Illuminate\Console\Command;

class ExpireBedReservations extends Command
{
    protected $signature = 'observation:expire-bed-reservations';
    protected $description = 'Expire overdue observation bed reservations.';
    public function handle(BedReservationService $service): int { $count = $service->expireReservations(); $this->info("Expired {$count} bed reservations."); return self::SUCCESS; }
}
