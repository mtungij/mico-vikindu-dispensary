<?php

namespace Tests\Feature\ClinicalEncounters;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Opd\Consultation;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LaboratorySampleRejectionReasonSeeder;
use Database\Seeders\LaboratoryTestCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SpecimenTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OpdWorkflowBlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_opd_cards_use_real_blockers_and_never_mutate_patient_work(): void
    {
        $admin = $this->bootstrappedFacility();
        foreach ([
            ['payment', 'PHA', 'awaiting_payment', 'Malipo / Cashier', 'Mgonjwa ana malipo'],
            ['pharmacy', 'PHA', 'awaiting_pharmacy', 'Pharmacy', 'dawa zinazosubiri Pharmacy'],
            ['laboratory', 'LAB', 'awaiting_lab', 'Laboratory', 'anasubiri huduma au majibu'],
            ['procedure', 'PRC', 'in_progress', 'Procedure Department', 'procedure inayosubiri'],
            ['admission', 'BED', 'under_observation', 'Observation / Bed', 'Observation / Bed'],
            ['completed', null, 'completed', 'Kumbukumbu za mgonjwa', 'Visit hii tayari imekamilika'],
        ] as [$reason, $code, $status, $destination, $message]) {
            $parent = $this->encounter($admin, $reason === 'payment');
            $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
            $parent->update(['department_id' => $opd->id, 'status' => 'completed', 'completed_at' => now()]);
            $followUp = $parent->replicate();
            $followUp->encounter_number .= '-FOLLOWUP';
            $followUp->parent_encounter_id = $parent->id;
            $followUp->encounter_type = 'follow_up';
            if ($reason === 'payment') {
                $followUp->id = 107;
            }
            $followUp->save();
            $visit = $parent->visit;
            $visit->patient->update(['first_name' => 'Rehema', 'last_name' => 'Ally']);
            $visit->update(['visit_status' => $status, 'current_department_id' => $opd->id, 'current_queue_id' => null]);
            if ($code) {
                $department = Department::query()->forCurrentFacility()->where('code', $code)->first();
                $department ??= Department::query()->create(['facility_id' => $visit->facility_id, 'name' => 'Procedures', 'code' => $code, 'department_type' => 'clinical', 'queue_enabled' => true]);
                PatientQueue::query()->forceCreate([
                    ...($reason === 'payment' ? ['id' => 354] : []),
                    'facility_id' => $visit->facility_id, 'patient_id' => $visit->patient_id, 'visit_id' => $visit->id,
                    'department_id' => $department->id, 'queue_number' => 'Q-'.$visit->id, 'queue_date' => today(),
                    'queue_status' => 'waiting', 'priority' => 'normal', 'position' => 1, 'checked_in_at' => now(), 'created_by' => $admin->id,
                ]);
            }
            if ($reason === 'payment') {
                $invoice = Invoice::query()->create([
                    'facility_id' => $visit->facility_id, 'patient_id' => $visit->patient_id, 'visit_id' => $visit->id,
                    'invoice_number' => 'INV-'.$visit->id, 'invoice_type' => 'mixed', 'payer_type' => 'cash',
                    'invoice_status' => 'partially_paid', 'total_amount' => 38000, 'paid_amount' => 34000,
                    'balance_amount' => 4000, 'currency' => 'TZS', 'created_by' => $admin->id, 'issued_at' => now(),
                ]);
                $method = PaymentMethod::query()->create([
                    'facility_id' => $visit->facility_id, 'name' => 'Cash', 'code' => 'CASH', 'type' => 'cash',
                    'is_cash' => true, 'is_active' => true, 'created_by' => $admin->id,
                ]);
                Payment::query()->create([
                    'facility_id' => $visit->facility_id, 'patient_id' => $visit->patient_id, 'visit_id' => $visit->id,
                    'invoice_id' => $invoice->id, 'payment_number' => 'PAY-153', 'payment_method_id' => $method->id,
                    'amount' => 34000, 'currency' => 'TZS', 'payment_date' => now(), 'status' => 'confirmed',
                    'received_by' => $admin->id, 'confirmed_by' => $admin->id, 'confirmed_at' => now(),
                ]);
                foreach ([['Completed OPD care', 34000, 34000], ['inj. tetanus 0.5mg', 4000, 0]] as [$description, $amount, $paid]) {
                    $invoice->items()->create([
                        'facility_id' => $visit->facility_id, 'patient_id' => $visit->patient_id, 'visit_id' => $visit->id,
                        'item_type' => 'service', 'description' => $description, 'quantity' => 1,
                        'unit_price' => $amount, 'total_amount' => $amount, 'payer_amount' => $amount,
                        'patient_amount' => $amount, 'paid_amount' => $paid, 'created_by' => $admin->id,
                    ]);
                }
            }
            $writes = [];
            $watchWrites = true;
            DB::listen(function ($query) use (&$writes, &$watchWrites): void {
                if ($watchWrites && preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                    $writes[] = $query->sql;
                }
            });
            $before = $this->workflowSnapshot();
            $response = $this->actingAs($admin)->get(route('opd.consultation', $visit));
            $response->assertOk()->assertSee('Consultation Imekamilika')->assertSee('Rehema Ally')
                ->assertSee($visit->patient->patient_number)->assertSee($visit->visit_number)->assertSee($message)
                ->assertSee($destination)->assertDontSee('403 Forbidden')->assertDontSee('Save Draft');
            if ($reason === 'payment') {
                $response->assertSee('TSh 4,000')->assertSee('Baada ya malipo: Pharmacy')
                    ->assertSee('Rehema Ally tayari amekamilisha OPD consultation na doctor follow-up.')
                    ->assertSee('VIS-2026-000128')->assertSee('Nenda Billing')->assertSee('Rudi OPD Queue')
                    ->assertSee(route('billing.index'))->assertDontSee('Nenda Pharmacy');
            }
            $this->get(route('opd.consultation', $visit))->assertOk();
            $this->assertSame($before, $this->workflowSnapshot());
            $this->assertSame([], $writes, 'Viewing workflow history must execute no database writes.');
            $watchWrites = false;
        }
    }

    public function test_blocked_page_preserves_security_and_has_only_authorized_navigation(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = $this->encounter($admin);
        $encounter->update(['department_id' => Department::query()->forCurrentFacility()->where('code', 'OPD')->value('id'), 'status' => 'completed']);
        $visit = $encounter->visit;
        $visit->update(['visit_status' => 'awaiting_payment']);
        Invoice::query()->create([
            'facility_id' => $visit->facility_id, 'patient_id' => $visit->patient_id, 'visit_id' => $visit->id,
            'invoice_number' => 'INV-PRIVATE', 'invoice_type' => 'mixed', 'payer_type' => 'cash',
            'invoice_status' => 'partially_paid', 'total_amount' => 38000, 'paid_amount' => 34000,
            'balance_amount' => 4000, 'currency' => 'TZS', 'created_by' => $admin->id, 'issued_at' => now(),
        ]);
        $doctor = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $doctor->id, 'facility_id' => $visit->facility_id]);
        $doctor->givePermissionTo('opd.consult');
        $before = $this->workflowSnapshot();
        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk()
            ->assertSee('Malipo / Cashier')->assertDontSee('Nenda Billing')->assertDontSee('Rudi OPD Queue')->assertDontSee('Angalia Patient / Clinical History');
        Livewire::actingAs($doctor)->test(Consultation::class, ['visit' => $visit])
            ->call('completeConsultation')->assertStatus(409);
        $this->assertSame($before, $this->workflowSnapshot());
        $unauthorized = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $unauthorized->id, 'facility_id' => $visit->facility_id]);
        $this->actingAs($unauthorized)->get(route('opd.consultation', $visit))->assertForbidden()->assertDontSee($visit->patient->fullName())->assertDontSee($visit->visit_number)
            ->assertDontSee('Malipo / Cashier')->assertDontSee('TSh 4,000')->assertDontSee('dawa zinazosubiri');
        $otherFacility = currentFacility()->replicate();
        $otherFacility->code = 'OTHER';
        $otherFacility->save();
        $doctor->staffProfile->update(['facility_id' => $otherFacility->id]);
        $doctor->unsetRelation('staffProfile');
        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden()->assertDontSee($visit->patient->fullName())->assertDontSee($visit->visit_number)
            ->assertDontSee('Malipo / Cashier')->assertDontSee('TSh 4,000')->assertDontSee('dawa zinazosubiri');
        $this->actingAs($admin)->get(route('opd.consultation', 999999999))->assertNotFound();
        $invalid = $this->visit($admin);
        $this->get(route('opd.consultation', $invalid))->assertStatus(409);
    }

    public function test_cross_facility_visit_and_encounter_policy_denial_remain_private_403s(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = $this->encounter($admin);
        $encounter->update(['status' => 'completed', 'encounter_type' => 'dental']);
        $doctor = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $doctor->id, 'facility_id' => currentFacility()->id]);
        $doctor->givePermissionTo('opd.consult');
        $visit = $encounter->visit;
        $visit->update(['visit_status' => 'awaiting_pharmacy']);
        $before = $this->workflowSnapshot();
        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden()
            ->assertDontSee($visit->patient->fullName())->assertDontSee($visit->visit_number)
            ->assertDontSee('dawa zinazosubiri');
        $this->assertSame($before, $this->workflowSnapshot());

        $other = currentFacility()->replicate();
        $other->code = 'FOREIGN';
        $other->save();
        $visit->update(['facility_id' => $other->id]);
        $before = $this->workflowSnapshot();
        $this->actingAs($admin)->get(route('opd.consultation', $visit))->assertForbidden()
            ->assertDontSee($visit->patient->fullName())->assertDontSee($visit->visit_number);
        $this->assertSame($before, $this->workflowSnapshot());
    }

    public function test_doctor_review_without_released_results_remains_a_read_only_conflict(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = $this->encounter($admin);
        $encounter->update(['status' => 'completed']);
        $visit = $encounter->visit;
        $visit->update(['visit_status' => 'awaiting_doctor_review']);
        $before = $this->workflowSnapshot();
        $this->actingAs($admin)->get(route('opd.consultation', $visit))->assertStatus(409)
            ->assertSee(__('opd_workflow.messages.conflict'));
        $this->assertSame($before, $this->workflowSnapshot());
    }

    private function workflowSnapshot(): array
    {
        $snapshot = [];
        foreach (['visits', 'clinical_encounters', 'patient_queues', 'invoices', 'invoice_items', 'payment_allocations', 'payments', 'prescriptions', 'dispensings', 'dispensing_items', 'prescription_items', 'stock_movements', 'medicine_batches', 'laboratory_orders', 'laboratory_order_items', 'laboratory_results', 'laboratory_result_values', 'laboratory_samples', 'clinical_procedure_orders', 'observation_admissions', 'activity_logs', 'service_prices'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
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

        $this->seed([
            PermissionSeeder::class,
            DepartmentSeeder::class,
            LaboratoryTestCategorySeeder::class,
            SpecimenTypeSeeder::class,
            LaboratorySampleRejectionReasonSeeder::class,
        ]);

        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function patient(User $admin): Patient
    {
        return Patient::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_number' => 'PAT-2026-'.fake()->unique()->numerify('######'),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'age_years' => 30,
            'patient_status' => 'active',
            'created_by' => $admin->id,
            'registered_at' => now(),
        ]);
    }

    private function visit(User $admin, bool $regression = false): Visit
    {
        $department = Department::query()->forCurrentFacility()->firstOrFail();

        return Visit::query()->forceCreate([
            ...($regression ? ['id' => 153] : []),
            'facility_id' => currentFacility()->id,
            'patient_id' => $this->patient($admin)->id,
            'visit_number' => $regression ? 'VIS-2026-000128' : 'VIS-2026-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $department->id,
            'current_department_id' => $department->id,
            'visit_status' => 'in_consultation',
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);
    }

    private function encounter(User $admin, bool $regression = false): ClinicalEncounter
    {
        $visit = $this->visit($admin, $regression);

        return ClinicalEncounter::query()->forceCreate([
            ...($regression ? ['id' => 105] : []),
            'facility_id' => currentFacility()->id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'encounter_type' => 'opd',
            'encounter_number' => 'ENC-2026-'.fake()->unique()->numerify('######'),
            'provider_user_id' => $admin->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'created_by' => $admin->id,
        ]);
    }
}
