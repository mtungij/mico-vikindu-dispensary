<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Development credentials only. Change these before using the system in production.
        User::query()->updateOrCreate(
            ['email' => 'admin@dispensary.test'],
            [
                'name' => 'System Administrator',
                'phone' => '0700000000',
                'password' => 'password',
                'status' => UserStatus::Active,
                'is_super_admin' => true,
            ],
        );

        $this->call([
            DemoFacilitySeeder::class,
            FacilitySettingsSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            DepartmentSeeder::class,
            WorkflowSettingsSeeder::class,
            JobTitleSeeder::class,
            InsuranceProviderSeeder::class,
            InsuranceSchemeSeeder::class,
            InsuranceRejectionReasonSeeder::class,
            InsuranceSettingsSeeder::class,
            ServiceCategorySeeder::class,
            ServiceSeeder::class,
            DentalFindingTypeSeeder::class,
            DentalProcedureTypeSeeder::class,
            DentalServiceSeeder::class,
            DentalMaterialSeeder::class,
            DentalAnaestheticSeeder::class,
            DentalConsentTemplateSeeder::class,
            DentalAppointmentTypeSeeder::class,
            DentalProcedureTemplateSeeder::class,
            DentalRoomSeeder::class,
            DentalChairSeeder::class,
            DentalSettingsSeeder::class,
            FacilitySettingsSeeder::class,
            MedicineCategorySeeder::class,
            GenericMedicineSeeder::class,
            DosageFormSeeder::class,
            MedicineUnitSeeder::class,
            MedicineRouteSeeder::class,
            StockLocationSeeder::class,
            ObservationRoomSeeder::class,
            BedSeeder::class,
            ObservationServiceSeeder::class,
            ObservationSettingsSeeder::class,
            ServicePriceSeeder::class,
            LaboratoryTestCategorySeeder::class,
            SpecimenTypeSeeder::class,
            LaboratorySampleRejectionReasonSeeder::class,
            MinimalIcd10Seeder::class,
            RolePermissionSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                DevelopmentLaboratoryTestSeeder::class,
                DevelopmentSupplierSeeder::class,
                DevelopmentMedicineSeeder::class,
                DevelopmentDentalSeeder::class,
                DemoStaffSeeder::class,
            ]);
        }
    }
}
