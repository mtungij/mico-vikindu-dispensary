<?php

namespace Tests\Feature;

use App\Livewire\Profile\ChangePassword;
use App\Livewire\Profile\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('name', 'Updated User')
            ->set('phone', '0712345678')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'phone' => '0712345678',
        ]);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'newpass123')
            ->set('password_confirmation', 'newpass123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('newpass123', $user->refresh()->password));
    }
}
