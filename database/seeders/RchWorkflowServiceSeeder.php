<?php

namespace Database\Seeders;

use App\Enums\RchService;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RchWorkflowServiceSeeder extends Seeder
{
    public function run(): void
    {
        Department::query()->where('code', 'RCH')->eachById(function (Department $department): void {
            DB::transaction(function () use ($department): void {
                Facility::query()->lockForUpdate()->findOrFail($department->facility_id);
                $category = ServiceCategory::query()->firstOrCreate(
                    ['facility_id' => $department->facility_id, 'code' => 'RCH'],
                    ['name' => 'RCH', 'category_type' => 'rch', 'is_active' => true],
                );

                foreach (RchService::cases() as $definition) {
                    $service = Service::withTrashed()->firstOrCreate(
                        ['facility_id' => $department->facility_id, 'code' => $definition->value],
                        [
                            'department_id' => $department->id,
                            'service_category_id' => $category->id,
                            'name' => $definition->catalogueName(),
                            'service_type' => $definition->serviceType(),
                            'requires_payment' => $definition->defaultPrice() > 0,
                            'is_active' => true,
                        ],
                    );

                    if ($service->trashed() || $service->service_type !== $definition->serviceType()) {
                        continue;
                    }

                    if ($service->department_id === null) {
                        $service->department_id = $department->id;
                    }

                    if (in_array($definition, [RchService::ChildMonitoring, RchService::FamilyPlanning], true)) {
                        $service->requires_payment = $definition->defaultPrice() > 0;
                    }

                    if ($service->isDirty()) {
                        $service->save();
                    }

                    if (! $service->prices()->withTrashed()->where('payer_type', 'cash')->exists()) {
                        $service->prices()->create([
                            'facility_id' => $department->facility_id,
                            'payer_type' => 'cash',
                            'amount' => $definition->defaultPrice(),
                            'currency' => 'TZS',
                            'is_active' => true,
                        ]);
                    }
                }
            });
        });
    }
}
