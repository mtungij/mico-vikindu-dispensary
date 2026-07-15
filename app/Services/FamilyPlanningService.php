<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\FamilyPlanningClient;
use App\Models\FamilyPlanningMethodEpisode;
use App\Models\FamilyPlanningVisit;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyPlanningService
{
    public function __construct(private readonly RchEncounterNumberService $numbers) {}

    public function register(Patient $patient, array $data, $actor): FamilyPlanningClient
    {
        return DB::transaction(function () use ($patient, $data, $actor): FamilyPlanningClient {
            $facilityId = currentFacility()?->id ?? $patient->facility_id;
            $client = FamilyPlanningClient::query()->create([
                'facility_id' => $facilityId,
                'patient_id' => $patient->id,
                'fp_client_number' => $this->numbers->familyPlanning($facilityId),
                'registration_date' => $data['registration_date'] ?? today(),
                'client_type' => $data['client_type'] ?? 'new',
                'reproductive_intention' => $data['reproductive_intention'] ?? null,
                'desired_number_of_children' => $data['desired_number_of_children'] ?? null,
                'spacing_preference' => $data['spacing_preference'] ?? null,
                'current_method_id' => $data['current_method_id'] ?? null,
                'status' => 'active',
                'registered_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'family_planning_client_registered', 'subject_type' => FamilyPlanningClient::class, 'subject_id' => $client->id]);
            return $client;
        });
    }

    public function recordVisit(FamilyPlanningClient $client, array $data, $actor): FamilyPlanningVisit
    {
        return DB::transaction(function () use ($client, $data, $actor): FamilyPlanningVisit {
            if (($data['visit_type'] ?? '') === 'discontinuation' && blank($data['discontinuation_reason'] ?? null)) {
                throw ValidationException::withMessages(['discontinuation_reason' => 'Sababu ya discontinuation inahitajika.']);
            }
            $selected = $data['selected_method_id'] ?? $client->current_method_id;
            $visit = FamilyPlanningVisit::query()->create([
                'facility_id' => $client->facility_id,
                'family_planning_client_id' => $client->id,
                'patient_id' => $client->patient_id,
                'visit_id' => $data['visit_id'] ?? null,
                'rch_encounter_id' => $data['rch_encounter_id'] ?? null,
                'visit_date' => $data['visit_date'] ?? today(),
                'visit_type' => $data['visit_type'] ?? 'follow_up',
                'current_method_id' => $client->current_method_id,
                'selected_method_id' => $selected,
                'method_start_date' => $data['method_start_date'] ?? today(),
                'expected_end_date' => $data['expected_end_date'] ?? null,
                'weight_kg' => $data['weight_kg'] ?? null,
                'systolic_bp' => $data['systolic_bp'] ?? null,
                'diastolic_bp' => $data['diastolic_bp'] ?? null,
                'pregnancy_test_status' => $data['pregnancy_test_status'] ?? null,
                'counselling_done' => $data['counselling_done'] ?? false,
                'eligibility_assessment' => $data['eligibility_assessment'] ?? null,
                'side_effects' => $data['side_effects'] ?? null,
                'complications' => $data['complications'] ?? null,
                'method_changed' => $selected && (int) $selected !== (int) $client->current_method_id,
                'previous_method_id' => $client->current_method_id,
                'discontinuation_reason' => $data['discontinuation_reason'] ?? null,
                'next_visit_date' => $data['next_visit_date'] ?? null,
                'provided_by' => $actor->id,
                'status' => $data['status'] ?? 'completed',
                'notes' => $data['notes'] ?? null,
            ]);
            if ($selected && (int) $selected !== (int) $client->current_method_id) {
                $client->methodEpisodes()->where('status', 'active')->update(['status' => 'ended', 'ended_at' => today(), 'ended_by' => $actor->id, 'discontinuation_reason' => $data['discontinuation_reason'] ?? 'Method changed']);
                FamilyPlanningMethodEpisode::query()->create(['facility_id' => $client->facility_id, 'family_planning_client_id' => $client->id, 'method_id' => $selected, 'started_at' => $data['method_start_date'] ?? today(), 'expected_end_at' => $data['expected_end_date'] ?? null, 'status' => 'active', 'started_by' => $actor->id, 'source_visit_id' => $visit->id]);
                $client->update(['current_method_id' => $selected]);
            }
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'family_planning_visit_recorded', 'subject_type' => FamilyPlanningVisit::class, 'subject_id' => $visit->id]);
            return $visit;
        });
    }
}
