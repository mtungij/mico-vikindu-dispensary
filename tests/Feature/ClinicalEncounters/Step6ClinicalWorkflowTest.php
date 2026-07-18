<?php

namespace Tests\Feature\ClinicalEncounters;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Livewire\Opd\Consultation as OpdConsultation;
use App\Livewire\Opd\Queue as OpdQueue;
use App\Livewire\Triage\Queue as TriageQueue;
use App\Models\ClinicalAlert;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Icd10Code;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use App\Models\MedicineUnit;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\PaymentConfirmationService;
use App\Services\ClinicalEncounterService;
use App\Services\PrescriptionService;
use App\Services\TriageService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MinimalIcd10Seeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
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

    public function test_assigned_opd_provider_can_open_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InConsultation);

        ClinicalEncounter::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'encounter_type' => 'opd',
            'encounter_number' => 'ENC-TEST-001',
            'provider_user_id' => $doctor->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'created_by' => $doctor->id,
        ]);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk();
    }

    public function test_authorized_opd_provider_from_same_facility_can_open_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))
            ->assertOk()
            ->assertSee('Visit Information')
            ->assertSee('Payment / Insurance')
            ->assertDontSee('Doctor Notes')
            ->assertDontSee('wire:model.live.debounce.2000ms="form.clinical_summary"', false);

        $this->assertDatabaseHas('clinical_encounters', [
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'provider_user_id' => $doctor->id,
        ]);
    }

    public function test_cashier_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $cashier = $this->staffUser('cashier');
        $visit = $this->opdVisit($admin);

        $this->actingAs($cashier)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_receptionist_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $receptionist = $this->staffUser('receptionist');
        $visit = $this->opdVisit($admin);

        $this->actingAs($receptionist)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_cross_facility_user_receives_403_for_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $otherFacility = Facility::query()->create([
            'name' => 'Other Dispensary',
            'code' => 'OTH',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000001',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $doctor = $this->staffUser('doctor', $otherFacility);
        $visit = $this->opdVisit($admin);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_patient_routed_to_opd_after_full_payment_can_be_opened(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['requires_triage' => false, 'queue_enabled' => true]);
        $invoice = $this->cashInvoiceForBillingVisit($admin, $opd, 10000);
        $cash = PaymentMethod::query()->create(['name' => 'Cash', 'code' => 'CASH_TEST', 'type' => 'cash', 'is_active' => true]);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 10000, $admin);

        $visit = $invoice->visit->refresh();
        $this->assertSame(VisitStatus::InProgress, $visit->visit_status);
        $this->assertSame($opd->id, $visit->current_department_id);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk();
    }

    public function test_visit_still_in_billing_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $visit = $this->visitInDepartment($admin, $billing, $opd, VisitStatus::Waiting);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_opd_summary_is_read_only_and_doctor_notes_are_in_plan_tab(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->assertSee('Patient Demographics')
            ->assertSee('Visit Information')
            ->assertSee('Latest Triage Vitals')
            ->assertSee('Payment / Insurance')
            ->assertDontSee('Doctor Plan')
            ->assertDontSee('wire:model.live.debounce.2000ms="form.clinical_summary"', false)
            ->set('activeTab', 'plan')
            ->assertSee('Doctor Plan')
            ->assertSee('Doctor notes / clinical summary');
    }

    public function test_orders_tab_separates_lab_catalogue_from_ordered_laboratory_tests(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $labService = $this->service('Full Blood Picture', 'FBP', 'laboratory_test', $admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);

        app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Rule out infection',
        ], $doctor);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit->refresh()])
            ->set('activeTab', 'orders')
            ->assertSee('Laboratory Test Catalogue')
            ->assertSee('Available Tests')
            ->assertSee('Ordered Laboratory Tests')
            ->assertSee('Full Blood Picture');
    }

    public function test_orders_tab_uses_medicine_catalogue_and_contains_referral_orders(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $medicine = $this->medicine($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Medication Orders')
            ->assertSee('Select medicine')
            ->assertSee($medicine->name)
            ->assertSee('Referral Orders')
            ->set('activeTab', 'follow')
            ->assertSee('Follow-up Appointment')
            ->assertDontSee('Referral Orders');
    }

    public function test_adding_lab_order_keeps_encounter_in_consultation_until_completion(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'MRDT', 'laboratory_test', $admin);

        app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Fever',
        ], $admin);

        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertSame(VisitStatus::InConsultation, $visit->refresh()->visit_status);
    }

    public function test_missing_opd_consult_permission_returns_403(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('opd.view-queue');
        $visit = $this->opdVisit($admin);

        $this->actingAs($user)->get(route('opd.consultation', $visit))->assertForbidden();
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
        $this->assertSame('booked', $appt->status->value);
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
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, MinimalIcd10Seeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }
        return $admin;
    }

    private function staffUser(string $roleName, ?Facility $facility = null): User
    {
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => ($facility ?? currentFacility())->id, 'user_id' => $user->id]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        return $user;
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

    private function opdVisit(User $admin, VisitStatus $status = VisitStatus::InProgress): Visit
    {
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();

        return $this->visitInDepartment($admin, $opd, $opd, $status);
    }

    private function visitInDepartment(User $admin, Department $currentDepartment, Department $destination, VisitStatus $status): Visit
    {
        $patient = $this->patient($admin);
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $destination->id,
            'current_department_id' => $currentDepartment->id,
            'visit_status' => $status,
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        if ($currentDepartment->code === 'OPD') {
            $queue = PatientQueue::query()->create([
                'facility_id' => currentFacility()->id,
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'department_id' => $currentDepartment->id,
                'queue_number' => 'OPD-TST-'.fake()->unique()->numerify('###'),
                'queue_date' => today(),
                'queue_status' => $status === VisitStatus::InConsultation ? QueueStatus::Serving : QueueStatus::Waiting,
                'priority' => 'normal',
                'position' => 1,
                'checked_in_at' => now(),
                'created_by' => $admin->id,
            ]);
            $visit->update(['current_queue_id' => $queue->id]);
        }

        return $visit->refresh();
    }

    private function cashInvoiceForBillingVisit(User $admin, Department $destination, int $amount): Invoice
    {
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $patient = $this->patient($admin);
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-BIL-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $destination->id,
            'current_department_id' => $billing->id,
            'visit_status' => VisitStatus::Waiting,
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $billing->id,
            'queue_number' => 'BIL-TST-'.fake()->unique()->numerify('###'),
            'queue_date' => today(),
            'queue_status' => QueueStatus::Waiting,
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);

        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'invoice_number' => 'INV-OPD-AUTH-'.fake()->unique()->numerify('######'),
            'payer_type' => 'cash',
            'invoice_status' => 'pending',
            'subtotal' => $amount,
            'patient_amount' => $amount,
            'total_amount' => $amount,
            'balance_amount' => $amount,
            'status' => 'open',
            'payment_status' => 'unpaid',
            'currency' => 'TZS',
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);

        InvoiceItem::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'item_type' => 'consultation',
            'description' => 'Consultation',
            'description_snapshot' => 'Consultation',
            'quantity' => 1,
            'unit_price' => $amount,
            'gross_amount' => $amount,
            'payer_amount' => $amount,
            'patient_amount' => $amount,
            'insurance_amount' => 0,
            'total_amount' => $amount,
            'net_amount' => $amount,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        return $invoice;
    }

    private function service(string $name, string $code, string $type, User $admin): Service
    {
        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Clinical', 'code' => 'CLIN', 'category_type' => 'consultation', 'is_active' => true, 'created_by' => $admin->id]);
        $service = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $name, 'code' => $code, 'service_type' => $type, 'requires_payment' => true, 'is_active' => true, 'created_by' => $admin->id]);
        ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'payer_type' => 'insurance', 'amount' => 1000, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);
        return $service;
    }

    private function medicine(User $admin): Medicine
    {
        $unit = MedicineUnit::query()->create([
            'facility_id' => currentFacility()->id,
            'name' => 'Tablet',
            'symbol' => 'tab',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return Medicine::query()->create([
            'facility_id' => currentFacility()->id,
            'purchase_unit_id' => $unit->id,
            'dispensing_unit_id' => $unit->id,
            'name' => 'Paracetamol',
            'code' => 'PCM-TEST',
            'strength' => '500mg',
            'pack_size' => 1,
            'purchase_to_dispensing_factor' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }
}
