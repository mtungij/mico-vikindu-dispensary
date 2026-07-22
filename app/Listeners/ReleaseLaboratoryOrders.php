<?php

namespace App\Listeners;

use App\Events\LaboratoryPaymentConfirmed;
use App\Services\LaboratoryPaymentReleaseService;

class ReleaseLaboratoryOrders
{
    public function __construct(private readonly LaboratoryPaymentReleaseService $release) {}

    public function handle(LaboratoryPaymentConfirmed $event): void
    {
        $this->release->releaseForInvoice($event->invoice, $event->actor);
    }
}
