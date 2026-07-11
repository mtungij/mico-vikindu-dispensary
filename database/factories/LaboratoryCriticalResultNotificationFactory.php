<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryCriticalResultNotification;
use App\Models\LaboratoryResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryCriticalResultNotificationFactory extends Factory
{
    protected $model = LaboratoryCriticalResultNotification::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'laboratory_result_id' => LaboratoryResult::factory(), 'notification_method' => 'phone', 'notified_by' => User::factory(), 'notified_at' => now(), 'status' => 'pending']; }
}
