<?php

namespace Database\Factories;

use App\Models\DentalAttachment;
use App\Models\DentalEncounter;
use Illuminate\Database\Eloquent\Factories\Factory;

class DentalAttachmentFactory extends Factory { protected $model = DentalAttachment::class; public function definition(): array { $e = DentalEncounter::factory()->create(); return ['facility_id'=>$e->facility_id,'patient_id'=>$e->patient_id,'dental_encounter_id'=>$e->id,'attachment_type'=>'intraoral_photo','title'=>'Photo','file_path'=>'dental/test.jpg','mime_type'=>'image/jpeg','file_size'=>100,'uploaded_by'=>$e->provider_user_id]; } }
