<?php

namespace App\Services;

use App\Models\InsuranceClaim;

class InsuranceReportService
{
    public function claimsQuery(array $filters = [])
    {
        return InsuranceClaim::query()
            ->forCurrentFacility()
            ->with(['patient', 'provider', 'scheme', 'membership', 'visit', 'items'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['provider_id'] ?? null, fn ($q, $id) => $q->where('insurance_provider_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('service_date_from', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('service_date_to', '<=', $to));
    }
}
