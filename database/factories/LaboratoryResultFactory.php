<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaboratoryResultFactory extends Factory
{
    protected $model = LaboratoryResult::class;
    public function definition(): array { return ['facility_id' => Facility::factory(), 'laboratory_order_id' => LaboratoryOrder::factory(), 'laboratory_order_item_id' => LaboratoryOrderItem::factory(), 'laboratory_test_id' => LaboratoryTest::factory(), 'result_status' => 'draft', 'result_version' => 1, 'created_by' => User::factory()]; }
}
