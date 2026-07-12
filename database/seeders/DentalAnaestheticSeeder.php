<?php

namespace Database\Seeders;

use App\Models\DentalAnaestheticType;
use Illuminate\Database\Seeder;

class DentalAnaestheticSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Lidocaine 2%','LIDOCAINE_2','Lidocaine','2%'], ['Lidocaine with Adrenaline','LIDO_ADRENALINE','Lidocaine','2% with adrenaline'], ['Articaine','ARTICAINE','Articaine',null], ['Bupivacaine','BUPIVACAINE','Bupivacaine',null]] as $row) {
            DentalAnaestheticType::query()->updateOrCreate(['facility_id'=>null,'code'=>$row[1]], ['name'=>$row[0],'generic_name'=>$row[2],'concentration'=>$row[3],'route'=>'injection','is_active'=>true]);
        }
    }
}
