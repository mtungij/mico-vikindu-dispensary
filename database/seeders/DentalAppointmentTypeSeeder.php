<?php

namespace Database\Seeders;

use App\Models\DentalAppointmentType;
use Illuminate\Database\Seeder;

class DentalAppointmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Dental Consultation','DENTAL_CONSULTATION',30],['Dental Procedure','DENTAL_PROCEDURE',60],['Post-extraction Review','POST_EXTRACTION_REVIEW',15],['Braces Adjustment','BRACES_ADJUSTMENT',30],['Root Canal Session','ROOT_CANAL_SESSION',60],['Crown Fitting','CROWN_FITTING',45],['Denture Fitting','DENTURE_FITTING',45],['Periodontal Review','PERIODONTAL_REVIEW',30],['Whitening Session','WHITENING_SESSION',45]] as $i => $row) {
            DentalAppointmentType::query()->updateOrCreate(['facility_id'=>null,'code'=>$row[1]], ['name'=>$row[0],'default_duration_minutes'=>$row[2],'is_system'=>true,'is_active'=>true,'sort_order'=>$i+1]);
        }
    }
}
