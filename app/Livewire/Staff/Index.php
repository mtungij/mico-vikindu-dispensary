<?php

namespace App\Livewire\Staff;

use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\ProfessionalLicenseStatus;
use App\Enums\UserStatus;
use App\Livewire\Forms\EmergencyContactForm;
use App\Livewire\Forms\StaffAccountForm;
use App\Livewire\Forms\StaffEducationForm;
use App\Livewire\Forms\StaffEmploymentForm;
use App\Livewire\Forms\StaffLicenseForm;
use App\Livewire\Forms\StaffPersonalForm;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Services\StaffComplianceService;
use App\Services\StaffNumberService;
use App\Services\StaffService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public StaffPersonalForm $personal;
    public StaffEmploymentForm $employment;
    public StaffAccountForm $account;
    public StaffEducationForm $educationForm;
    public StaffLicenseForm $licenseForm;
    public EmergencyContactForm $contactForm;

    public string $search = '';
    public string $department = '';
    public string $jobTitle = '';
    public string $role = '';
    public string $employmentStatus = '';
    public string $accountStatus = '';
    public string $licenseStatus = '';
    public int $perPage = 10;
    public string $sort = 'name';

    public bool $showCreateModal = false;
    public int $step = 1;
    public ?TemporaryUploadedFile $passportPhoto = null;
    public array $additionalDepartments = [];
    public array $educationRecords = [];
    public array $licenses = [];
    public array $emergencyContacts = [];
    public ?string $generatedPassword = null;
    public ?string $createdStaffUrl = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'department' => ['except' => ''],
        'jobTitle' => ['except' => ''],
        'role' => ['except' => ''],
        'employmentStatus' => ['except' => ''],
        'accountStatus' => ['except' => ''],
        'licenseStatus' => ['except' => ''],
        'perPage' => ['except' => 10],
        'sort' => ['except' => 'name'],
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', StaffProfile::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(StaffNumberService $numbers, \App\Services\StaffPasswordService $passwords): void
    {
        Gate::authorize('create', StaffProfile::class);
        $this->resetCreateState();
        $this->personal->employee_number = auth()->user()->can('staff.override-employee-number') ? $numbers->next() : null;
        $this->account->temporary_password = $passwords->generateTemporaryPassword();
        $this->account->temporary_password_confirmation = $this->account->temporary_password;
        $this->showCreateModal = true;
    }

    public function generatePassword(\App\Services\StaffPasswordService $passwords): void
    {
        $this->account->temporary_password = $passwords->generateTemporaryPassword();
        $this->account->temporary_password_confirmation = $this->account->temporary_password;
    }

    public function nextStep(): void
    {
        $this->validateStep();
        $this->step = min(6, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function addAdditionalDepartment(): void
    {
        $this->additionalDepartments[] = [
            'department_id' => null,
            'can_receive_queue' => false,
            'can_manage_department' => false,
        ];
    }

    public function removeAdditionalDepartment(int $index): void
    {
        unset($this->additionalDepartments[$index]);
        $this->additionalDepartments = array_values($this->additionalDepartments);
    }

    public function addEducation(): void
    {
        $data = $this->educationForm->data();
        if (($data['is_highest_qualification'] ?? false) === true) {
            foreach ($this->educationRecords as &$record) {
                $record['is_highest_qualification'] = false;
            }
        }
        $this->educationRecords[] = $data;
        $this->educationForm->reset();
    }

    public function addLicense(): void
    {
        $this->licenses[] = $this->licenseForm->data();
        $this->licenseForm->reset();
    }

    public function addEmergencyContact(): void
    {
        $data = $this->contactForm->data();
        if (($data['is_primary'] ?? false) === true) {
            foreach ($this->emergencyContacts as &$contact) {
                $contact['is_primary'] = false;
            }
        }
        $this->emergencyContacts[] = $data;
        $this->contactForm->reset();
    }

    public function save(StaffService $service): void
    {
        Gate::authorize('create', StaffProfile::class);
        $this->validateStep(true);
        $this->validate([
            'passportPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $result = $service->createStaff([
            'personal' => $this->personal->data(),
            'employment' => $this->employment->data(),
            'account' => $this->account->data(),
            'departments' => $this->additionalDepartments,
            'education' => $this->educationRecords,
            'licenses' => $this->licenses,
            'emergency_contacts' => $this->emergencyContacts,
        ], auth()->user());

        $staff = $result['staff'];
        if ($this->passportPhoto instanceof TemporaryUploadedFile) {
            $path = $this->passportPhoto->storeAs(
                "staff/{$staff->facility_id}/{$staff->id}/profile",
                str()->uuid()->toString().'.'.strtolower($this->passportPhoto->getClientOriginalExtension()),
                'local',
            );
            $staff->update(['passport_photo_path' => $path]);
            $staff->user->update(['avatar_path' => $path]);
        }

        $this->generatedPassword = $result['temporary_password'];
        $this->createdStaffUrl = route('staff.show', $staff);
        $this->showCreateModal = false;
        Notifier::success('staff.created');
    }

    public function activate(StaffProfile $staffProfile, StaffService $service): void
    {
        Gate::authorize('activate', $staffProfile);
        $service->activateAccount($staffProfile, auth()->user());
        Notifier::success('staff.account_activated');
    }

    public function suspend(StaffProfile $staffProfile, StaffService $service): void
    {
        Gate::authorize('suspend', $staffProfile);
        $service->suspendAccount($staffProfile, auth()->user(), 'Suspended from staff list');
        Notifier::success('staff.account_suspended');
    }

    public function deactivate(StaffProfile $staffProfile, StaffService $service): void
    {
        Gate::authorize('delete', $staffProfile);
        $service->deactivateAccount($staffProfile, auth()->user());
        Notifier::success('staff.account_deactivated');
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    private function validateStep(bool $all = false): void
    {
        if ($all || $this->step === 1) {
            $this->personal->validate();
        }
        if ($all || $this->step === 2) {
            $this->employment->validate();
        }
        if ($all || $this->step === 3) {
            $this->account->validate();
        }
    }

    private function resetCreateState(): void
    {
        $this->personal->resetForm();
        $this->employment->reset();
        $this->account->reset();
        $this->educationForm->reset();
        $this->licenseForm->reset();
        $this->contactForm->reset();
        $this->account->create_login_account = true;
        $this->account->status = UserStatus::Active->value;
        $this->account->must_change_password = true;
        $this->step = 1;
        $this->passportPhoto = null;
        $this->additionalDepartments = [];
        $this->educationRecords = [];
        $this->licenses = [];
        $this->emergencyContacts = [];
        $this->generatedPassword = null;
        $this->createdStaffUrl = null;
    }

    public function render(StaffComplianceService $compliance): View
    {
        $staff = StaffProfile::query()
            ->forCurrentFacility()
            ->with(['user.roles', 'employmentRecord.jobTitle', 'employmentRecord.primaryDepartment'])
            ->withCount(['educationRecords', 'professionalLicenses', 'documents', 'emergencyContacts'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('middle_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('employee_number', 'like', "%{$this->search}%")
                        ->orWhere('primary_phone', 'like', "%{$this->search}%")
                        ->orWhere('personal_email', 'like', "%{$this->search}%")
                        ->orWhere('nida_number', 'like', "%{$this->search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('email', 'like', "%{$this->search}%"))
                        ->orWhereHas('employmentRecord.jobTitle', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('employmentRecord.primaryDepartment', fn ($query) => $query->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->department !== '', fn ($query) => $query->whereHas('employmentRecord', fn ($query) => $query->where('primary_department_id', $this->department)))
            ->when($this->jobTitle !== '', fn ($query) => $query->whereHas('employmentRecord', fn ($query) => $query->where('job_title_id', $this->jobTitle)))
            ->when($this->role !== '', fn ($query) => $query->whereHas('user.roles', fn ($query) => $query->where('roles.id', $this->role)))
            ->when($this->employmentStatus !== '', fn ($query) => $query->whereHas('employmentRecord', fn ($query) => $query->where('employment_status', $this->employmentStatus)))
            ->when($this->accountStatus !== '', fn ($query) => $query->whereHas('user', fn ($query) => $query->where('status', $this->accountStatus)))
            ->when($this->licenseStatus !== '', function ($query): void {
                if ($this->licenseStatus === 'missing') {
                    $query->whereDoesntHave('professionalLicenses', fn ($query) => $query->where('status', 'active'));
                } else {
                    $query->whereHas('professionalLicenses', fn ($query) => $query->where('status', $this->licenseStatus));
                }
            })
            ->when($this->sort === 'recent', fn ($query) => $query->latest())
            ->when($this->sort === 'employee_number', fn ($query) => $query->orderBy('employee_number'))
            ->when($this->sort === 'name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
            ->paginate($this->perPage);

        return view('livewire.staff.index', [
            'staff' => $staff,
            'departments' => Department::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'jobTitles' => JobTitle::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::query()->forCurrentFacility()->where('is_active', true)->orderBy('display_name')->get(),
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'genders' => Gender::cases(),
            'maritalStatuses' => MaritalStatus::cases(),
            'employmentCategories' => EmploymentCategory::cases(),
            'employmentStatuses' => EmploymentStatus::cases(),
            'accountStatuses' => UserStatus::cases(),
            'educationLevels' => EducationLevel::cases(),
            'licenseStatuses' => ProfessionalLicenseStatus::cases(),
            'compliance' => $compliance,
        ])->layout('components.layouts.app', [
            'title' => __('staff.title'),
            'description' => __('staff.description'),
        ]);
    }
}
