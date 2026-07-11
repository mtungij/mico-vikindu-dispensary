<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_active_super_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'name' => 'System Administrator',
            'email' => 'admin@dispensary.test',
            'phone' => '0700000000',
            'status' => UserStatus::Active->value,
            'is_super_admin' => true,
        ]);
    }
}
