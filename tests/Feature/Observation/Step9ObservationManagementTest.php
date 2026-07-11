<?php

namespace Tests\Feature\Observation;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Observation\BedBoard;
use App\Models\Bed;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MedicationAdministration;
use App\Models\ObservationAdmission;
use App\Models\ObservationRoom;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use App\Models\Visit;
use App\Services\BedManagementService;
use App\Services\BedReservationService;
use App\Services\IntakeOutputService;
use App\Services\IvFluidService;
use App\Services\MedicationAdministrationService;
use App\Services\NursingObservationService;
use App\Services\ObservationAdmissionService;
use App\Services\ObservationDischargeService;
use Database\Seeders\BedSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ObservationRoomSeeder;
use Database\Seeders\ObservationServiceSeeder;
use Database\Seeders\ObservationSettingsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step9ObservationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_observation_settings(): void
    {
        $this->get(route('settings.observation.rooms'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_render_observation_pages(): void
    {
        $admin = $this->bootstrappedFacility();
        Livewire::actingAs($admin)->test(BedBoard::class)->assertOk();
        $this->actingAs($admin)->get(route('observation.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('observation.index'))->assertOk();
        $this->actingAs($admin)->get(route('settings.observation.rooms'))->assertOk();
        $this->actingAs($admin)->get(route('settings.observation.beds'))->assertOk();
        $this->actingAs($admin)->get(route('reports.observation.admissions'))->assertOk();
    }

    public function test_admission_assigns_bed_generates_number_updates_status_and_audit(): void
    {
        $admin = $this->bootstrappedFacility();
        [$patient, $visit] = $this->patientVisit($admin);
        $bed = Bed::query()->forCurrentFacility()->where('status', 'available')->firstOrFail();

        $admission = app(ObservationAdmissionService::class)->admit($patient, $visit, [
            'admission_type' => 'hourly_observation',
            'reason_for_admission' => 'Short observation',
            'bed_id' => $bed->id,
            'acuity_level' => 'low',
        ], $admin);

        $this->assertStringStartsWith('OBS-', $admission->admission_number);
        $this->assertSame('under_observation', $admission->status->value);
        $this->assertSame('occupied', $bed->refresh()->status->value);
        $this->assertDatabaseHas('bed_assignments', ['observation_admission_id' => $admission->id, 'assignment_status' => 'active']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'observation_admitted', 'subject_id' => $admission->id]);
    }

    public function test_patient_cannot_have_two_active_observation_admissions(): void
    {
        $admin = $this->bootstrappedFacility();
        [$patient, $visit] = $this->patientVisit($admin);
        app(ObservationAdmissionService::class)->admit($patient, $visit, ['admission_type' => 'hourly_observation', 'reason_for_admission' => 'Observation'], $admin);

        $this->expectException(ValidationException::class);
        app(ObservationAdmissionService::class)->admit($patient, $visit, ['admission_type' => 'hourly_observation', 'reason_for_admission' => 'Second'], $admin);
    }

    public function test_reservation_expires_and_releases_bed(): void
    {
        $admin = $this->bootstrappedFacility();
        [$patient, $visit] = $this->patientVisit($admin);
        $bed = Bed::query()->forCurrentFacility()->where('status', 'available')->firstOrFail();
        app(BedManagementService::class)->reserveBed($bed, $patient, $visit, $admin, expiresAt: now()->subMinute());

        $count = app(BedReservationService::class)->expireReservations();

        $this->assertSame(1, $count);
        $this->assertSame('available', $bed->refresh()->status->value);
    }

    public function test_transfer_preserves_history_and_moves_source_to_cleaning(): void
    {
        $admin = $this->bootstrappedFacility();
        [$patient, $visit] = $this->patientVisit($admin);
        $beds = Bed::query()->forCurrentFacility()->where('status', 'available')->take(2)->get();
        $admission = app(ObservationAdmissionService::class)->admit($patient, $visit, ['admission_type' => 'hourly_observation', 'reason_for_admission' => 'Observation', 'bed_id' => $beds[0]->id], $admin);

        app(BedManagementService::class)->transferBed($admission, $beds[1], $admin, 'Needs quieter bed');

        $this->assertSame('cleaning', $beds[0]->refresh()->status->value);
        $this->assertSame('occupied', $beds[1]->refresh()->status->value);
        $this->assertDatabaseCount('bed_assignments', 2);
    }

    public function test_nursing_observation_creates_critical_alert_for_low_spo2(): void
    {
        $admin = $this->bootstrappedFacility();
        $admission = $this->admission($admin);

        app(NursingObservationService::class)->record($admission, ['oxygen_saturation' => 82, 'temperature' => 37, 'notes' => 'Low oxygen'], $admin);

        $this->assertDatabaseHas('clinical_alerts', ['patient_id' => $admission->patient_id, 'alert_type' => 'observation_abnormal_vital']);
    }

    public function test_medication_iv_and_intake_output_are_recorded_separately_from_pharmacy(): void
    {
        $admin = $this->bootstrappedFacility();
        $admission = $this->admission($admin);

        $med = app(MedicationAdministrationService::class)->schedule($admission, ['medicine_name_snapshot' => 'Paracetamol', 'dose' => '500mg', 'route' => 'oral'], $admin);
        app(MedicationAdministrationService::class)->administer($med, $admin);
        app(IvFluidService::class)->start($admission, ['fluid_name_snapshot' => 'Normal Saline', 'volume_ml' => 500], $admin);
        app(IntakeOutputService::class)->record($admission, ['record_type' => 'intake', 'volume_ml' => 250], $admin);
        app(IntakeOutputService::class)->record($admission, ['record_type' => 'urine', 'volume_ml' => 100], $admin);

        $this->assertSame('administered', $med->refresh()->administration_status->value);
        $this->assertSame(150.0, app(IntakeOutputService::class)->balance($admission));
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => MedicationAdministration::class]);
    }

    public function test_discharge_releases_bed_to_cleaning_and_print_routes_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $admission = $this->admission($admin);

        $discharge = app(ObservationDischargeService::class)->discharge($admission, ['discharge_type' => 'home', 'discharge_condition' => 'stable', 'final_diagnosis' => 'Recovered'], $admin);

        $this->assertSame('discharged', $admission->refresh()->status->value);
        $this->assertSame('cleaning', $admission->bed->refresh()->status->value);
        $this->actingAs($admin)->get(route('observation.chart.print', $admission))->assertOk();
        $this->actingAs($admin)->get(route('observation.discharges.print', $discharge))->assertOk();
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, ObservationRoomSeeder::class, BedSeeder::class, ObservationServiceSeeder::class, ObservationSettingsSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) $admin->givePermissionTo($permission);
        return $admin;
    }

    private function patientVisit(User $admin): array
    {
        $facility = currentFacility();
        $department = Department::query()->where('facility_id', $facility->id)->where('code', 'BED')->firstOrFail();
        $patient = Patient::factory()->create(['facility_id'=>$facility->id,'created_by'=>$admin->id]);
        $visit = Visit::factory()->create(['facility_id'=>$facility->id,'patient_id'=>$patient->id,'visit_type'=>'new_patient','payer_type'=>'cash','destination_department_id'=>$department->id,'current_department_id'=>$department->id,'visit_status'=>'awaiting_bed','created_by'=>$admin->id]);
        return [$patient, $visit];
    }

    private function admission(User $admin): ObservationAdmission
    {
        [$patient, $visit] = $this->patientVisit($admin);
        $bed = Bed::query()->forCurrentFacility()->where('status','available')->firstOrFail();
        return app(ObservationAdmissionService::class)->admit($patient, $visit, ['admission_type'=>'hourly_observation','reason_for_admission'=>'Observation','bed_id'=>$bed->id], $admin);
    }
}
