<?php

namespace Tests\Feature\ClinicalEncounters;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\VisitStatus;
use App\Livewire\Opd\Queue as OpdQueue;
use App\Livewire\Triage\Queue as TriageQueue;
use App\Models\ClinicalAlert;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\PrescriptionService;
use App\Services\TriageService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MinimalIcd10Seeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class Step6ClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_triage(): void
    {
        $this->get(route('triage.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_triage_and_opd_queues(): void
    {
        $admin = $this->bootstrappedFacility();
        Livewire::actingAs($admin)->test(TriageQueue::class)->assertOk();
        Livewire::actingAs($admin)->test(OpdQueue::class)->assertOk();
    }

    public function test_triage_assessment_calculates_bmi_creates_alert_and_moves_visit_to_opd_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);

        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $assessment = app(TriageService::class)->completeAssessment($assessment, [
            'triage_level' => 'urgent',
            'chief_complaint_summary' => 'Fever and shortness of breath',
            'temperature' => 39.8,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'pulse_rate' => 110,
            'oxygen_saturation' => 88,
            'weight_kg' => 80,
            'height_cm' => 180,
            'pain_score' => 7,
            'danger_signs' => [],
        ], $admin);

        $this->assertSame('24.69', $assessment->bmi);
        $this->assertDatabaseHas('clinical_alerts', ['visit_id' => $visit->id, 'alert_type' => 'abnormal_vital']);
        $this->assertSame(VisitStatus::InQueue, $visit->refresh()->visit_status);
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $visit->id, 'queue_status' => 'waiting']);
    }

    public function test_invalid_pain_score_and_oxygen_saturation_are_rejected(): void
    {
        $this->bootstrappedFacility();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\VitalSignAssessmentService::class)->validateVitalRanges(['pain_score' => 11, 'oxygen_saturation' => 101]);
    }

    public function test_clinician_can_start_save_signoff_and_complete_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $service = app(ClinicalEncounterService::class);

        $encounter = $service->startEncounter($visit, $admin);
        $service->saveDraft($encounter, ['clinical_summary' => 'Stable patient', 'treatment_plan' => 'Oral medication', 'outcome' => 'discharged_home'], $admin);
        $service->addDiagnosis($encounter->refresh(), ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true], $admin);
        $service->signOff($encounter->refresh(), $admin);
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $this->assertSame('completed', $completed->status->value);
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
    }

    public function test_two_clinicians_cannot_start_same_department_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ClinicalEncounterService::class)->startEncounter($visit->refresh(), User::factory()->create());
    }

    public function test_completed_encounter_is_immutable_without_amend_permission(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $service = app(ClinicalEncounterService::class);
        $encounter = $service->startEncounter($visit, $admin);
        $service->saveDraft($encounter, ['clinical_summary' => 'Summary', 'treatment_plan' => 'Plan', 'outcome' => 'discharged_home'], $admin);
        $service->addDiagnosis($encounter->refresh(), ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed'], $admin);
        $service->signOff($encounter->refresh(), $admin);
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $user = User::factory()->create();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->saveDraft($completed, ['clinical_summary' => 'Changed'], $user);
    }

    public function test_diagnosis_primary_is_unique_and_icd_import_is_idempotent(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter($this->visit($admin, VisitStatus::InQueue), $admin);
        $service = app(\App\Services\DiagnosisService::class);
        $first = $service->addDiagnosis($encounter, ['diagnosis_type' => 'provisional', 'diagnosis_name' => 'Malaria', 'certainty' => 'probable', 'is_primary' => true], $admin);
        $second = $service->addDiagnosis($encounter, ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true], $admin);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);

        $path = tempnam(sys_get_temp_dir(), 'icd');
        file_put_contents($path, "code,title\nZ99,Test code\nZ99,Test code updated\n");
        Artisan::call('icd10:import', ['file' => $path]);
        Artisan::call('icd10:import', ['file' => $path]);
        $this->assertSame(1, Icd10Code::query()->where('code', 'Z99')->count());
    }

    public function test_lab_order_prescription_procedure_followup_and_referral_foundations_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'LAB001', 'laboratory_test', $admin);
        $procedure = $this->service('Dressing', 'PROC001', 'procedure', $admin);
        $clinical = app(ClinicalEncounterService::class);

        $lab = $clinical->addLabOrder($encounter->refresh(), ['service_ids' => [$labService->id], 'clinical_notes' => 'Rule out malaria'], $admin);
        $rx = $clinical->addPrescription($encounter->refresh(), ['items' => [['medication_name' => 'Paracetamol', 'dose' => '500mg', 'frequency' => 'TDS', 'duration_value' => 3, 'duration_unit' => 'days']]], $admin);
        $proc = $clinical->addProcedureOrder($encounter->refresh(), ['service_id' => $procedure->id, 'procedure_name_snapshot' => 'Dressing'], $admin);
        $appt = $clinical->createFollowUp($encounter->refresh(), ['scheduled_start' => now()->addDay()->format('Y-m-d H:i:s'), 'department_id' => $encounter->department_id], $admin);
        $ref = $clinical->createReferral($encounter->refresh(), ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Specialist review', 'urgency' => 'urgent'], $admin);

        $this->assertStringStartsWith('LAB-', $lab->order_number);
        $this->assertStringStartsWith('RX-', $rx->prescription_number);
        $this->assertSame('Dressing', $proc->procedure_name_snapshot);
        $this->assertSame('scheduled', $appt->status->value);
        $this->assertStringStartsWith('REF-', $ref->referral_number);
    }

    public function test_routes_for_prints_dashboard_and_reports_render(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter($this->visit($admin, VisitStatus::InQueue), $admin);
        $referral = app(ClinicalEncounterService::class)->createReferral($encounter, ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Review', 'urgency' => 'routine'], $admin);

        $this->actingAs($admin)->get(route('opd.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('clinical-encounters.print', $encounter))->assertOk();
        $this->actingAs($admin)->get(route('referrals.print', $referral))->assertOk();
        $this->actingAs($admin)->get(route('reports.triage.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.opd.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.diagnoses.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.referrals.export'))->assertOk();
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create([
            'name' => 'James Medical Dispensary',
            'code' => 'JMD',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'physical_address' => 'Kijitonyama',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, MinimalIcd10Seeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }
        return $admin;
    }

    private function patient(User $admin): Patient
    {
        return Patient::query()->create(['facility_id' => currentFacility()->id, 'patient_number' => 'PAT-2026-'.fake()->unique()->numerify('######'), 'first_name' => 'Test', 'last_name' => 'Patient', 'gender' => 'male', 'age_years' => 30, 'patient_status' => 'active', 'created_by' => $admin->id, 'registered_at' => now()]);
    }

    private function visit(User $admin, VisitStatus $status = VisitStatus::AwaitingTriage): Visit
    {
        $department = Department::query()->forCurrentFacility()->firstOrFail();
        $department->update(['clinical_department' => true, 'queue_enabled' => true]);
        return Visit::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $this->patient($admin)->id, 'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'), 'visit_type' => 'new_patient', 'payer_type' => 'insurance', 'destination_department_id' => $department->id, 'current_department_id' => $department->id, 'visit_status' => $status, 'priority' => 'normal', 'registered_at' => now(), 'created_by' => $admin->id]);
    }

    private function service(string $name, string $code, string $type, User $admin): Service
    {
        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Clinical', 'code' => 'CLIN', 'category_type' => 'consultation', 'is_active' => true, 'created_by' => $admin->id]);
        $service = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $name, 'code' => $code, 'service_type' => $type, 'requires_payment' => true, 'is_active' => true, 'created_by' => $admin->id]);
        ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'payer_type' => 'insurance', 'amount' => 1000, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);
        return $service;
    }
}
