<?php

namespace Database\Seeders;

use App\Models\DentalChair;
use App\Models\DentalRoom;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class DentalChairSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        $room = DentalRoom::query()->where('facility_id', $facility->id)->first();
        if (! $room) return;
        foreach ([['Dental Chair 1','DENTAL_CHAIR_1'], ['Dental Chair 2','DENTAL_CHAIR_2']] as $row) {
            DentalChair::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$row[1]], ['dental_room_id'=>$room->id,'name'=>$row[0],'status'=>'available','is_active'=>true]);
        }
    }
}
