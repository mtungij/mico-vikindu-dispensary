<?php

namespace Tests\Feature\Appointments;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Appointments\Create as AppointmentCreate;
use App\Livewire\Appointments\DepartmentSchedule as AppointmentDepartmentSchedule;
use App\Livewire\Appointments\DoctorSchedule as AppointmentDoctorSchedule;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AppointmentService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step12AppointmentSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_can_be_booked_from_livewire_form(): void
    {
        [$admin, $patient, $department, $doctor] = $this->setupSchedulingContext();

        Livewire::actingAs($admin)
            ->test(AppointmentCreate::class)
            ->set('form.patient_id', $patient->id)
            ->set('form.department_id', $department->id)
            ->set('form.staff_id', $doctor->id)
            ->set('form.appointment_type', 'general_consultation')
            ->set('form.appointment_date', now()->addDay()->toDateString())
            ->set('form.appointment_time', '09:00')
            ->set('form.reason', 'Headache review')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'staff_id' => $doctor->id,
            'status' => 'booked',
        ]);
        $this->assertStringStartsWith('APT-'.now()->format('Y').'-', Appointment::query()->firstOrFail()->appointment_number);
    }

    public function test_appointment_pages_render_and_sidebar_is_visible_for_authorized_user(): void
    {
        [$admin] = $this->setupSchedulingContext();
        $this->actingAs($admin);

        foreach ([
            'appointments.dashboard',
            'appointments.index',
            'appointments.book',
            'appointments.calendar',
            'appointments.doctor-schedules',
            'appointments.department-schedules',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('appointments.dashboard'))
            ->assertSee('Appointments Dashboard')
            ->assertSee('Calendar')
            ->assertSee('All Appointments')
            ->assertSee('Book Appointment')
            ->assertSee('Doctor Schedules')
            ->assertSee('Department Schedules');
    }

    public function test_sidebar_hides_appointment_menu_for_unauthorized_user(): void
    {
        $user = User::factory()->create();
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$user->id,'updated_by'=>$user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Appointments Dashboard')
            ->assertDontSee('Book Appointment');
    }

    public function test_double_booking_same_doctor_same_slot_is_blocked(): void
    {
        [$admin, $patient, $department, $doctor] = $this->setupSchedulingContext();
        $service = app(AppointmentService::class);
        $slot = ['patient_id'=>$patient->id,'department_id'=>$department->id,'staff_id'=>$doctor->id,'appointment_type'=>'general_consultation','appointment_date'=>now()->addDay()->toDateString(),'appointment_time'=>'10:00','estimated_duration'=>30,'priority'=>'normal'];

        $service->create($slot, $admin);

        $this->expectException(ValidationException::class);
        $service->create($slot, $admin);
    }

    public function test_cancel_and_check_in_create_visit(): void
    {
        [$admin, $patient, $department, $doctor] = $this->setupSchedulingContext();
        $service = app(AppointmentService::class);
        $appointment = $service->create(['patient_id'=>$patient->id,'department_id'=>$department->id,'staff_id'=>$doctor->id,'appointment_type'=>'follow_up_visit','appointment_date'=>now()->addDay()->toDateString(),'appointment_time'=>'11:00','estimated_duration'=>30,'priority'=>'normal'], $admin);

        $service->cancel($appointment, 'Patient requested cancellation', $admin);
        $this->assertSame('cancelled', $appointment->refresh()->status->value);

        $second = $service->create(['patient_id'=>$patient->id,'department_id'=>$department->id,'staff_id'=>$doctor->id,'appointment_type'=>'follow_up_visit','appointment_date'=>now()->addDay()->toDateString(),'appointment_time'=>'12:00','estimated_duration'=>30,'priority'=>'normal'], $admin);
        $service->checkIn($second, $admin);

        $this->assertNotNull($second->refresh()->visit_id);
        $this->assertDatabaseHas('visits', ['id' => $second->visit_id, 'source' => 'appointment']);
        $this->assertDatabaseMissing('invoices', ['visit_id' => $second->visit_id]);
    }

    public function test_doctor_and_department_schedule_modals_save(): void
    {
        [$admin, , $department, $doctor] = $this->setupSchedulingContext();

        Livewire::actingAs($admin)
            ->test(AppointmentDoctorSchedule::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->set('staff_id', $doctor->id)
            ->set('department_id', $department->id)
            ->set('working_day', 'monday')
            ->set('slot_duration', 30)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        Livewire::actingAs($admin)
            ->test(AppointmentDepartmentSchedule::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->set('department_id', $department->id)
            ->set('working_day', 'monday')
            ->set('slot_duration', 30)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('doctor_schedules', ['staff_id' => $doctor->id, 'department_id' => $department->id, 'working_day' => 'monday']);
        $this->assertDatabaseHas('department_schedules', ['department_id' => $department->id, 'working_day' => 'monday']);
    }

    private function setupSchedulingContext(): array
    {
        $admin = User::factory()->superAdmin()->create();
        $facility = Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        $department = Department::query()->where('facility_id', $facility->id)->where('is_active', true)->first()
            ?? Department::factory()->create(['facility_id' => $facility->id, 'queue_enabled' => true, 'clinical_department' => true, 'can_receive_patients' => true]);
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'created_by' => $admin->id]);
        $doctor = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => $facility->id, 'user_id' => $doctor->id]);

        return [$admin, $patient, $department, $doctor];
    }
}
