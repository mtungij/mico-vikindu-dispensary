<?php

namespace Database\Seeders;

use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use App\Models\Department;
use App\Models\Facility;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        $admin = User::query()->where('is_super_admin', true)->first();

        if (! $facility) {
            return;
        }

        foreach ($this->titles() as $index => $title) {
            $department = isset($title['department_code'])
                ? Department::query()->where('facility_id', $facility->id)->where('code', $title['department_code'])->first()
                : null;
            unset($title['department_code']);

            JobTitle::query()->updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $title['code']],
                array_merge($title, [
                    'facility_id' => $facility->id,
                    'department_id' => $department?->id,
                    'sort_order' => $index + 1,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ]),
            );
        }
    }

    private function titles(): array
    {
        return [
            ['name' => 'System Administrator', 'code' => 'SYSADM', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::Diploma, 'department_code' => 'ADM'],
            ['name' => 'Facility Manager', 'code' => 'FM', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::Bachelor, 'department_code' => 'ADM'],
            ['name' => 'Receptionist', 'code' => 'RECPT', 'employment_category' => EmploymentCategory::Permanent, 'department_code' => 'REC'],
            ['name' => 'Cashier', 'code' => 'CASH', 'employment_category' => EmploymentCategory::Permanent, 'department_code' => 'BIL'],
            ['name' => 'Accountant', 'code' => 'ACC', 'employment_category' => EmploymentCategory::Permanent, 'department_code' => 'ACC'],
            ['name' => 'Medical Doctor', 'code' => 'MD', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::Bachelor, 'is_clinical' => true, 'requires_professional_license' => true, 'license_authority' => 'Medical Council of Tanganyika', 'department_code' => 'OPD'],
            ['name' => 'Clinical Officer', 'code' => 'CO', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::Diploma, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'OPD'],
            ['name' => 'Assistant Medical Officer', 'code' => 'AMO', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::AdvancedDiploma, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'OPD'],
            ['name' => 'Registered Nurse', 'code' => 'RN', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::Diploma, 'is_clinical' => true, 'requires_professional_license' => true, 'license_authority' => 'Tanzania Nursing and Midwifery Council', 'department_code' => 'TRI'],
            ['name' => 'Enrolled Nurse', 'code' => 'EN', 'employment_category' => EmploymentCategory::Permanent, 'minimum_education_level' => EducationLevel::Certificate, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'BED'],
            ['name' => 'Laboratory Technician', 'code' => 'LABT', 'employment_category' => EmploymentCategory::Permanent, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'LAB'],
            ['name' => 'Laboratory Scientist', 'code' => 'LABS', 'employment_category' => EmploymentCategory::Permanent, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'LAB'],
            ['name' => 'Pharmacist', 'code' => 'PHARM', 'employment_category' => EmploymentCategory::Permanent, 'requires_professional_license' => true, 'license_authority' => 'Pharmacy Council', 'department_code' => 'PHA'],
            ['name' => 'Pharmaceutical Technician', 'code' => 'PHT', 'employment_category' => EmploymentCategory::Permanent, 'requires_professional_license' => true, 'department_code' => 'PHA'],
            ['name' => 'Dental Officer', 'code' => 'DO', 'employment_category' => EmploymentCategory::Permanent, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'DEN'],
            ['name' => 'Dental Therapist', 'code' => 'DT', 'employment_category' => EmploymentCategory::Permanent, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'DEN'],
            ['name' => 'RCH Nurse', 'code' => 'RCHN', 'employment_category' => EmploymentCategory::Permanent, 'is_clinical' => true, 'requires_professional_license' => true, 'department_code' => 'RCH'],
            ['name' => 'Store Keeper', 'code' => 'SK', 'employment_category' => EmploymentCategory::Permanent, 'department_code' => 'STR'],
            ['name' => 'Records Officer', 'code' => 'RO', 'employment_category' => EmploymentCategory::Permanent, 'department_code' => 'REC'],
        ];
    }
}
