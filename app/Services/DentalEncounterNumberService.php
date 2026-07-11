<?php

namespace App\Services;

class DentalEncounterNumberService
{
    public function __construct(private readonly SequenceNumberService $numbers) {}
    public function next(int $facilityId): string { return $this->numbers->next('dental_encounter_number_sequences', $facilityId, 'DEN', 6); }
    public function plan(int $facilityId): string { return $this->numbers->next('dental_plan_number_sequences', $facilityId, 'DTP', 6); }
    public function procedure(int $facilityId): string { return $this->numbers->next('dental_procedure_number_sequences', $facilityId, 'DPR', 6); }
    public function orthodonticCase(int $facilityId): string { return $this->numbers->next('orthodontic_case_number_sequences', $facilityId, 'ORT', 6); }
    public function labOrder(int $facilityId): string { return $this->numbers->next('dental_lab_order_number_sequences', $facilityId, 'DLB', 6); }
}
