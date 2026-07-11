<?php

namespace App\Events;

use App\Models\Facility;
use Illuminate\Foundation\Events\Dispatchable;

class FacilityUpdated
{
    use Dispatchable;

    public function __construct(public Facility $facility, public array $oldValues = []) {}
}
