<?php

namespace App\Services;

class ClinicalEncounterNumberService
{
    public function __construct(private readonly SequenceNumberService $numbers) {}
    public function next(int $facilityId): string { return $this->numbers->next('clinical_encounter_number_sequences', $facilityId, 'ENC', 6); }
}
