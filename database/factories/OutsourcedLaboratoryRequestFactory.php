<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryOrderItem;
use App\Models\OutsourcedLaboratoryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutsourcedLaboratoryRequestFactory extends Factory
{
    protected $model = OutsourcedLaboratoryRequest::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'laboratory_order_item_id' => LaboratoryOrderItem::factory(), 'external_provider_name' => fake()->company(), 'status' => 'prepared', 'created_by' => User::factory()]; }
}
