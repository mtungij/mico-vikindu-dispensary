<?php

namespace App\Services;

use App\Models\DentalMaterial;

class DentalMaterialService
{
    public function save(array $data, $actor): DentalMaterial
    {
        return DentalMaterial::query()->updateOrCreate(['facility_id'=>currentFacility()->id,'code'=>strtoupper($data['code'])], [...$data,'facility_id'=>currentFacility()->id,'code'=>strtoupper($data['code']),'created_by'=>$actor?->id,'updated_by'=>$actor?->id]);
    }
}
