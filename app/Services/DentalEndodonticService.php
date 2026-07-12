<?php

namespace App\Services;

use App\Models\DentalEncounter;
use App\Models\DentalEndodonticCase;

class DentalEndodonticService
{
    public function __construct(private readonly DentalEncounterNumberService $numbers) {}

    public function create(DentalEncounter $encounter, array $data, $actor): DentalEndodonticCase
    {
        return DentalEndodonticCase::query()->create(['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'dental_encounter_id'=>$encounter->id,'case_number'=>$this->numbers->endodonticCase($encounter->facility_id),'tooth_number'=>$data['tooth_number'],'diagnosis'=>$data['diagnosis'],'canals_expected'=>$data['canals_expected'] ?? null,'canals_found'=>$data['canals_found'] ?? null,'working_length_details'=>$data['working_length_details'] ?? null,'status'=>$data['status'] ?? 'planned','provider_user_id'=>$actor->id,'notes'=>$data['notes'] ?? null]);
    }
}
