<?php

namespace Database\Seeders;

use App\Models\DentalRoom;
use App\Models\Department;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class DentalRoomSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        $department = Department::query()->where('facility_id', $facility->id)->where('code', 'DEN')->first();
        foreach ([['Dental Room 1','DENTAL_ROOM_1'], ['Oral Surgery Room','ORAL_SURGERY_ROOM']] as $row) {
            DentalRoom::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$row[1]], ['department_id'=>$department?->id,'name'=>$row[0],'is_active'=>true]);
        }
    }
}
