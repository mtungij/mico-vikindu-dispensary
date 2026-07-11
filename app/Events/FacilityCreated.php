<?php

namespace App\Events;

use App\Models\Facility;
use Illuminate\Foundation\Events\Dispatchable;

class FacilityCreated
{
    use Dispatchable;

    public function __construct(public Facility $facility) {}
}
