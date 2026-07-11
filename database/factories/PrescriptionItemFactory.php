<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionItemFactory extends Factory
{
    protected $model = PrescriptionItem::class;
    public function definition(): array { return ['prescription_id' => Prescription::factory(), 'medication_name' => 'Paracetamol', 'dose' => '500mg', 'frequency' => 'TDS', 'duration_value' => 3, 'duration_unit' => 'days', 'status' => 'prescribed', 'created_by' => User::factory()]; }
}
