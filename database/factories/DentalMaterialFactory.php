<?php

namespace Database\Factories;

use App\Models\DentalMaterial;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalMaterialFactory extends Factory { protected $model = DentalMaterial::class; public function definition(): array { return ['facility_id'=>Facility::query()->first()?->id ?? Facility::factory()->create()->id,'name'=>fake()->word(),'code'=>strtoupper(fake()->unique()->lexify('MAT???')),'category'=>'restorative','unit'=>'pcs','is_active'=>true]; } }
