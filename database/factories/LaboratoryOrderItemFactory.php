<?php

namespace Database\Factories;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryOrderItemFactory extends Factory
{
    protected $model = LaboratoryOrderItem::class;
    public function definition(): array { return ['laboratory_order_id' => LaboratoryOrder::factory(), 'service_id' => Service::factory(), 'test_name_snapshot' => 'Malaria test', 'unit_price_snapshot' => 0, 'payer_amount' => 0, 'patient_amount' => 0, 'created_by' => User::factory()]; }
}
