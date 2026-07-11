<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ObservationRoom;
use Illuminate\Database\Seeder;

class ObservationRoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            $department = Department::query()->where('facility_id', $facility->id)->where('code', 'BED')->first();
            foreach ([['General Observation','GEN','general_observation',3,false],['Emergency Observation','EMG','emergency_observation',1,false],['Pediatric Observation','PED','pediatric_observation',1,false],['Female Observation','FEM','female_observation',1,false],['Male Observation','MAL','male_observation',1,false],['Isolation Room','ISO','isolation',1,true]] as [$name,$code,$type,$capacity,$isolation]) {
                ObservationRoom::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$code], ['department_id'=>$department?->id,'name'=>$name,'room_type'=>$type,'capacity'=>$capacity,'isolation_room'=>$isolation,'gender_restriction'=>match($code){'FEM'=>'female','MAL'=>'male','PED'=>'pediatric',default=>'any'},'is_active'=>true]);
            }
        }
    }
}
