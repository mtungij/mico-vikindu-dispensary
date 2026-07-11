<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Facility;
use App\Models\ObservationRoom;
use Illuminate\Database\Seeder;

class BedSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            $map = [['OBS-01','GEN','standard'],['OBS-02','GEN','standard'],['OBS-03','GEN','standard'],['PED-01','PED','pediatric'],['FEM-01','FEM','standard'],['MAL-01','MAL','standard'],['ISO-01','ISO','isolation']];
            foreach ($map as [$code,$roomCode,$type]) {
                $room = ObservationRoom::query()->where('facility_id',$facility->id)->where('code',$roomCode)->first();
                if (! $room) continue;
                Bed::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$code], ['observation_room_id'=>$room->id,'name'=>$code,'bed_type'=>$type,'gender_restriction'=>$room->gender_restriction,'status'=>'available','current_cleaning_status'=>'clean','is_active'=>true]);
            }
        }
    }
}
