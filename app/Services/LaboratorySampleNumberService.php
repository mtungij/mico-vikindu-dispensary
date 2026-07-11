<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LaboratorySampleNumberService
{
    public function __construct(private readonly SequenceNumberService $numbers) {}
    public function next(int $facilityId): string { return $this->numbers->next('laboratory_sample_number_sequences', $facilityId, config('facility.laboratory_sample_prefix', 'SMP'), (int) config('facility.laboratory_sample_padding', 6), (bool) config('facility.laboratory_sample_include_year', true)); }
}
