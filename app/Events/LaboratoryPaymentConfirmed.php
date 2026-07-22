<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LaboratoryPaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Invoice $invoice, public User $actor) {}
}
