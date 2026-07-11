<?php

namespace Database\Seeders;

use App\Enums\LaboratoryResultType;
use App\Models\Facility;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\SpecimenType;
use Illuminate\Database\Seeder;

class DevelopmentLaboratoryTestSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            $category = ServiceCategory::query()->where('facility_id', $facility->id)->where('code', 'LAB')->first();
            $haematology = LaboratoryTestCategory::query()->where('facility_id', $facility->id)->where('code', 'HEMA')->first();
            $parasitology = LaboratoryTestCategory::query()->where('facility_id', $facility->id)->where('code', 'PARA')->first();
            $serology = LaboratoryTestCategory::query()->where('facility_id', $facility->id)->where('code', 'SERO')->first();
            $wholeBlood = SpecimenType::query()->where('facility_id', $facility->id)->where('code', 'WB')->first();
            $serum = SpecimenType::query()->where('facility_id', $facility->id)->where('code', 'SER')->first();

            if (! $category || ! $haematology || ! $wholeBlood) {
                continue;
            }

            $tests = [
                ['Full Blood Picture', 'FBP', 'laboratory_test', $haematology, $wholeBlood, 120, 15000, [
                    ['Haemoglobin', 'HB', LaboratoryResultType::Numeric, 'g/dL', 6, 18, 60, 240],
                    ['White Blood Cells', 'WBC', LaboratoryResultType::Numeric, '10^9/L', 4, 11, 1, 50],
                    ['Platelets', 'PLT', LaboratoryResultType::Numeric, '10^9/L', 150, 450, 20, 1000],
                ]],
                ['Malaria MRDT', 'MRDT', 'laboratory_test', $parasitology ?: $haematology, $wholeBlood, 30, 5000, [
                    ['Malaria Antigen', 'MALAG', LaboratoryResultType::PositiveNegative, null, null, null, null, null],
                ]],
                ['HIV Rapid Test', 'HIVR', 'laboratory_test', $serology ?: $haematology, $serum ?: $wholeBlood, 45, 10000, [
                    ['HIV Result', 'HIV', LaboratoryResultType::ReactiveNonReactive, null, null, null, null, null],
                ]],
            ];

            foreach ($tests as [$name, $code, $type, $testCategory, $specimen, $tat, $price, $parameters]) {
                $service = Service::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'code' => $code],
                    ['service_category_id' => $category->id, 'name' => $name, 'service_type' => $type, 'requires_payment' => true, 'is_active' => true],
                );

                ServicePrice::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'service_id' => $service->id, 'payer_type' => 'cash', 'insurance_provider_id' => null, 'corporate_account_id' => null],
                    ['amount' => $price, 'currency' => 'TZS', 'is_active' => true],
                );

                $test = LaboratoryTest::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'code' => $code],
                    [
                        'service_id' => $service->id,
                        'laboratory_test_category_id' => $testCategory->id,
                        'specimen_type_id' => $specimen->id,
                        'name' => $name,
                        'result_type' => count($parameters) > 1 ? LaboratoryResultType::Composite : $parameters[0][2],
                        'turnaround_time_minutes' => $tat,
                        'is_active' => true,
                    ],
                );

                foreach ($parameters as $index => [$parameterName, $parameterCode, $resultType, $unit, $low, $high, $criticalLow, $criticalHigh]) {
                    $parameter = $test->parameters()->updateOrCreate(
                        ['code' => $parameterCode],
                        [
                            'facility_id' => $facility->id,
                            'name' => $parameterName,
                            'result_type' => $resultType,
                            'unit' => $unit,
                            'critical_low' => $criticalLow,
                            'critical_high' => $criticalHigh,
                            'sort_order' => $index + 1,
                            'is_active' => true,
                        ],
                    );

                    if ($low !== null || $high !== null) {
                        LaboratoryReferenceRange::query()->updateOrCreate(
                            ['facility_id' => $facility->id, 'laboratory_test_id' => $test->id, 'laboratory_test_parameter_id' => $parameter->id, 'gender' => null],
                            ['lower_limit' => $low, 'upper_limit' => $high, 'unit' => $unit, 'is_active' => true],
                        );
                    }
                }
            }
        }
    }
}
