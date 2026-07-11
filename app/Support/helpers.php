<?php

use App\Models\Facility;
use App\Services\FacilityContext;

if (! function_exists('currentFacility')) {
    function currentFacility(): ?Facility
    {
        return app(FacilityContext::class)->current();
    }
}
