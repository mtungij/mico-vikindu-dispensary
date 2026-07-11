<?php

namespace App\Events;

use App\Models\Facility;
use Illuminate\Foundation\Events\Dispatchable;

class FacilityBrandingUpdated
{
    use Dispatchable;

    public function __construct(public Facility $facility) {}
}
