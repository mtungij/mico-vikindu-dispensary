<?php

namespace Database\Factories;

use App\Models\DentalFindingType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalFindingTypeFactory extends Factory { protected $model = DentalFindingType::class; public function definition(): array { return ['facility_id'=>Facility::query()->first()?->id,'code'=>strtoupper(fake()->unique()->lexify('FND???')),'name'=>fake()->words(2,true),'category'=>'caries','applies_to_surface'=>true,'applies_to_whole_tooth'=>true,'is_active'=>true]; } }
