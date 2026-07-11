<?php

namespace App\Services;

class PatientNumberService
{
    public function __construct(private readonly SequenceNumberService $sequences) {}

    public function next(?int $facilityId = null): string
    {
        return $this->sequences->next('patient_number_sequences', $facilityId ?? currentFacility()?->id, 'PAT', 6);
    }
}
