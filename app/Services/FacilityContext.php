<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Support\Facades\Cache;

class FacilityContext
{
    private ?Facility $facility = null;

    public function current(): ?Facility
    {
        if ($this->facility !== null) {
            return $this->facility;
        }

        $facilityId = Cache::rememberForever('facility.current_id', function (): ?int {
            return Facility::query()->orderBy('id')->value('id');
        });

        if ($facilityId === null) {
            return null;
        }

        $facility = Facility::query()
                ->with(['settings', 'documents.uploader'])
                ->find($facilityId);

        if ($facility === null) {
            $this->forget();
            return null;
        }

        return $this->facility = $facility;
    }

    public function forget(): void
    {
        $this->facility = null;
        Cache::forget('facility.current');
        Cache::forget('facility.current_id');
    }
}
