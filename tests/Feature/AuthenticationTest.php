<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create(['email' => 'active@example.test']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->suspended()->create(['email' => 'suspended@example.test']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'inactive@example.test']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_updates_last_login_fields(): void
    {
        $user = User::factory()->create(['email' => 'login@example.test']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.10'])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertSame('127.0.0.10', $user->last_login_ip);
    }

    public function test_active_user_middleware_logs_out_non_active_user(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Pending]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
