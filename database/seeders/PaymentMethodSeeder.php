<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['Cash','CASH','cash',false,false,false,true,1],
            ['M-Pesa','MPESA','mobile_money',true,true,false,false,2],
            ['Airtel Money','AIRTEL_MONEY','mobile_money',true,true,false,false,3],
            ['Tigo Pesa / Mixx by Yas','MIX_BY_YAS','mobile_money',true,true,false,false,4],
            ['HaloPesa','HALOPESA','mobile_money',true,true,false,false,5],
            ['Bank Transfer','BANK_TRANSFER','bank_transfer',true,false,true,false,6],
            ['POS / Card','CARD','card',true,false,false,false,7],
            ['Cheque','CHEQUE','cheque',true,false,true,false,8],
            ['Patient Deposit','PATIENT_DEPOSIT','patient_deposit',false,false,false,false,9],
            ['Other','OTHER','other',false,false,false,false,99],
        ];

        foreach ($methods as [$name, $code, $type, $ref, $phone, $bank, $cash, $sort]) {
            PaymentMethod::query()->updateOrCreate(
                ['facility_id' => null, 'code' => $code],
                ['name' => $name, 'type' => $type, 'requires_reference' => $ref, 'requires_phone' => $phone, 'requires_bank' => $bank, 'is_cash' => $cash, 'is_system' => true, 'is_active' => true, 'sort_order' => $sort],
            );
        }
    }
}
