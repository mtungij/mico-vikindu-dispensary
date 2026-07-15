<?php

namespace Tests\Feature\Billing;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Billing\Cashier\Sessions as CashierSessions;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\BillingSettingsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierSessionOpeningFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_session_opening_flow_creates_active_session(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)
            ->test(CashierSessions::class)
            ->call('create')
            ->set('openForm.shift', 'morning')
            ->set('openForm.opening_float', '1000')
            ->set('openForm.cash_drawer', 'Main Counter')
            ->call('openSession')
            ->assertHasNoErrors()
            ->assertSet('showOpen', false);

        $this->assertDatabaseHas('cashier_sessions', [
            'user_id' => $admin->id,
            'shift' => 'morning',
            'cash_drawer' => 'Main Counter',
            'status' => 'open',
        ]);
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, PaymentMethodSeeder::class, BillingSettingsSeeder::class]);

        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }
}
