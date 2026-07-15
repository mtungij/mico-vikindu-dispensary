<?php

namespace App\Services;

class RchEncounterNumberService
{
    public function __construct(private readonly SequenceNumberService $numbers) {}
    public function next(int $facilityId): string { return $this->numbers->next('rch_encounter_sequences', $facilityId, 'RCH'); }
    public function pregnancy(int $facilityId): string { return $this->numbers->next('pregnancy_sequences', $facilityId, 'PRG'); }
    public function familyPlanning(int $facilityId): string { return $this->numbers->next('family_planning_sequences', $facilityId, 'FP'); }
    public function child(int $facilityId): string { return $this->numbers->next('rch_child_sequences', $facilityId, 'CWC'); }
}
