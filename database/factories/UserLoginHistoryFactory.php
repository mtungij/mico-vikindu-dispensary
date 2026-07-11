<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserLoginHistory> */
class UserLoginHistoryFactory extends Factory
{
    protected $model = UserLoginHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email_attempted' => fake()->safeEmail(),
            'status' => 'successful',
            'ip_address' => '127.0.0.1',
            'logged_in_at' => now(),
        ];
    }
}
