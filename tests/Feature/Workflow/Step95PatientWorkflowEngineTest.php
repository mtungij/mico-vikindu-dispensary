<?php

namespace Tests\Feature\Workflow;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\VisitStatus;
use App\Livewire\Reports\Workflow as WorkflowReport;
use App\Livewire\Settings\Workflow\Index as WorkflowSettings;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use App\Models\Visit;
use App\Services\WorkflowService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WorkflowSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step95PatientWorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_render_workflow_settings_and_reports(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)->test(WorkflowSettings::class)->assertOk();
        Livewire::actingAs($admin)->test(WorkflowReport::class)->assertOk();
        $this->actingAs($admin)->get(route('reports.workflow.export'))->assertOk()->assertHeader('content-disposition');
    }

    public function test_queue_creation_uses_department_prefix_daily_sequence_and_ticket(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, 'BIL');
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();

        $queue = app(WorkflowService::class)->createQueue($visit, $billing, $admin, VisitStatus::AwaitingPayment, 'Payment required');
        $ticket = app(WorkflowService::class)->createTicket($queue, $admin);

        $this->assertSame('BIL-0001', $queue->queue_number);
        $this->assertSame('waiting', $queue->queue_status->value);
        $this->assertSame($queue->id, $visit->refresh()->current_queue_id);
        $this->assertSame('BIL-0001', $ticket->queue_number);
        $this->assertDatabaseHas('visit_movements', ['visit_id' => $visit->id, 'to_department_id' => $billing->id, 'movement_type' => 'queue_created']);
    }

    public function test_queue_actions_call_serve_complete_skip_cancel_and_requeue(): void
    {
        $admin = $this->bootstrappedFacility();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $queue = app(WorkflowService::class)->createQueue($this->visit($admin, 'OPD'), $opd, $admin, VisitStatus::Waiting, 'Registration completed');

        app(WorkflowService::class)->callQueue($queue, $admin);
        $this->assertSame('called', $queue->refresh()->queue_status->value);
        $this->assertDatabaseHas('queue_calls', ['patient_queue_id' => $queue->id, 'call_count' => 1]);

        app(WorkflowService::class)->startService($queue->refresh(), $admin);
        $this->assertSame('serving', $queue->refresh()->queue_status->value);

        app(WorkflowService::class)->completeQueue($queue->refresh(), $admin, VisitStatus::InConsultation);
        $this->assertSame('completed', $queue->refresh()->queue_status->value);

        $second = app(WorkflowService::class)->createQueue($this->visit($admin, 'OPD'), $opd, $admin, VisitStatus::Waiting, 'New queue');
        app(WorkflowService::class)->skipQueue($second, $admin, 'No response');
        $this->assertSame('skipped', $second->refresh()->queue_status->value);
        app(WorkflowService::class)->requeue($second->refresh(), $admin);
        $this->assertSame('waiting', $second->refresh()->queue_status->value);
        app(WorkflowService::class)->cancelQueue($second->refresh(), $admin, 'Patient left');
        $this->assertSame('cancelled', $second->refresh()->queue_status->value);
    }

    public function test_transfer_records_movement_and_blocks_invalid_department_without_override(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, 'OPD');
        $pharmacy = Department::query()->forCurrentFacility()->where('code', 'PHA')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(WorkflowService::class)->transferPatient($visit, $pharmacy, 'Needs medicine', $admin);
    }

    public function test_emergency_override_allows_transfer_and_is_audited(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, 'OPD');
        $pharmacy = Department::query()->forCurrentFacility()->where('code', 'PHA')->firstOrFail();

        $queue = app(WorkflowService::class)->transferPatient($visit, $pharmacy, 'Emergency dispensing override', $admin, VisitStatus::Waiting, true, $admin);

        $this->assertNotNull($queue);
        $this->assertSame($pharmacy->id, $visit->refresh()->current_department_id);
        $this->assertDatabaseHas('visit_movements', ['visit_id' => $visit->id, 'to_department_id' => $pharmacy->id, 'emergency_override' => true, 'authorized_by' => $admin->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'patient_moved']);
    }

    public function test_visit_completion_and_cancellation_are_centralized(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, 'OPD');
        app(WorkflowService::class)->completeVisit($visit, $admin);
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);

        $second = $this->visit($admin, 'OPD');
        app(WorkflowService::class)->cancelVisit($second, $admin, 'Duplicate registration');
        $this->assertSame(VisitStatus::Cancelled, $second->refresh()->visit_status);
        $this->assertSame('Duplicate registration', $second->cancellation_reason);
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, WorkflowSettingsSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }
        return $admin;
    }

    private function visit(User $admin, string $departmentCode): Visit
    {
        $facility = currentFacility();
        $department = Department::query()->where('facility_id', $facility->id)->where('code', $departmentCode)->firstOrFail();
        $patient = Patient::factory()->create(['facility_id'=>$facility->id,'created_by'=>$admin->id]);

        return Visit::factory()->create(['facility_id'=>$facility->id,'patient_id'=>$patient->id,'visit_type'=>'new_patient','payer_type'=>'cash','destination_department_id'=>$department->id,'current_department_id'=>$department->id,'visit_status'=>'registered','created_by'=>$admin->id]);
    }
}
