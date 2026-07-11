<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Collection;

class ClinicalTimelineService
{
    public function forPatient(Patient $patient): Collection
    {
        $events = collect();
        $patient->visits()->with(['latestTriageAssessment', 'clinicalEncounters.diagnoses', 'clinicalEncounters.laboratoryOrders', 'clinicalEncounters.prescriptions', 'clinicalEncounters.procedureOrders', 'clinicalEncounters.referrals', 'clinicalEncounters.appointments'])->latest()->limit(20)->get()
            ->each(function ($visit) use ($events): void {
                $events->push(['type' => 'visit', 'date' => $visit->registered_at, 'title' => $visit->visit_number, 'summary' => $visit->visit_status->value]);
                if ($visit->latestTriageAssessment) {
                    $events->push(['type' => 'triage', 'date' => $visit->latestTriageAssessment->assessed_at, 'title' => 'Triage', 'summary' => $visit->latestTriageAssessment->triage_level->value]);
                }
                foreach ($visit->clinicalEncounters as $encounter) {
                    $events->push(['type' => 'encounter', 'date' => $encounter->started_at, 'title' => $encounter->encounter_number, 'summary' => $encounter->status->value]);
                }
            });

        return $events->sortByDesc('date')->values();
    }
}
