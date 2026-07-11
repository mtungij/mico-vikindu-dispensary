<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Facility;
use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_active_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $this->completedFacility($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashibodi')
            ->assertSee('Wagonjwa Leo');
    }

    public function test_main_layout_renders_toaster_hub(): void
    {
        $user = User::factory()->create();
        $this->completedFacility($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="toaster"', false);
    }

    private function completedFacility(User $user): Facility
    {
        return Facility::query()->create([
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
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
