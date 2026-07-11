<?php

namespace Database\Factories;

use App\Models\DentalMaterial;
use App\Models\DentalProcedure;
use App\Models\DentalProcedureMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalProcedureMaterialFactory extends Factory { protected $model = DentalProcedureMaterial::class; public function definition(): array { $p = DentalProcedure::factory()->create(); $m = DentalMaterial::factory()->create(['facility_id'=>$p->facility_id]); return ['dental_procedure_id'=>$p->id,'dental_material_id'=>$m->id,'quantity'=>1,'unit_snapshot'=>$m->unit,'created_by'=>$p->created_by]; } }
