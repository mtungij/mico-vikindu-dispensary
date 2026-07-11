<?php

namespace App\Services;

class VisitNumberService
{
    public function __construct(private readonly SequenceNumberService $sequences) {}
    public function next(?int $facilityId = null): string { return $this->sequences->next('visit_number_sequences', $facilityId ?? currentFacility()?->id, 'VIS', 6); }
}
