<?php

namespace App\Livewire\Staff;

use App\Enums\StaffDocumentType;
use App\Livewire\Forms\StaffDocumentForm;
use App\Models\ActivityLog;
use App\Models\StaffProfile;
use App\Services\StaffComplianceService;
use App\Services\StaffDocumentService;
use App\Services\StaffPasswordService;
use App\Services\StaffSignatureService;
use App\Services\StaffService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public StaffProfile $staffProfile;
    public StaffDocumentForm $documentForm;
    public string $tab = 'summary';
    public bool $showDocumentModal = false;
    public bool $showSignatureModal = false;
    public bool $showPasswordModal = false;
    public ?TemporaryUploadedFile $documentFile = null;
    public ?TemporaryUploadedFile $signatureFile = null;
    public ?string $temporaryPassword = null;

    protected $queryString = [
        'tab' => ['except' => 'summary'],
    ];

    public function mount(StaffProfile $staffProfile): void
    {
        Gate::authorize('view', $staffProfile);
        $this->staffProfile = $staffProfile->load([
            'user.roles.permissions',
            'user.permissions',
            'employmentRecord.jobTitle',
            'employmentRecord.primaryDepartment',
            'educationRecords',
            'professionalLicenses',
            'documents',
            'activeSignature',
            'signatures',
            'emergencyContacts',
        ]);
    }

    public function openDocumentModal(): void
    {
        Gate::authorize('manageDocuments', $this->staffProfile);
        $this->documentForm->reset();
        $this->documentForm->document_type = StaffDocumentType::Other->value;
        $this->documentFile = null;
        $this->showDocumentModal = true;
    }

    public function uploadDocument(StaffDocumentService $service): void
    {
        Gate::authorize('manageDocuments', $this->staffProfile);
        $this->validate([
            'documentFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $service->store($this->staffProfile, $this->documentFile, $this->documentForm->data(), auth()->user());
        $this->showDocumentModal = false;
        $this->refreshProfile();
        Notifier::success('documents.uploaded');
    }

    public function openSignatureModal(): void
    {
        Gate::authorize('manageSignature', $this->staffProfile);
        $this->signatureFile = null;
        $this->showSignatureModal = true;
    }

    public function uploadSignature(StaffSignatureService $service): void
    {
        Gate::authorize('manageSignature', $this->staffProfile);
        $this->validate([
            'signatureFile' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ]);

        $service->store($this->staffProfile, $this->signatureFile, auth()->user());
        $this->showSignatureModal = false;
        $this->signatureFile = null;
        $this->refreshProfile();
        Notifier::success('staff.signature_saved');
    }

    public function deleteSignature(StaffSignatureService $service): void
    {
        Gate::authorize('manageSignature', $this->staffProfile);
        $service->deleteActive($this->staffProfile, auth()->user());
        $this->refreshProfile();
        Notifier::success('staff.signature_deleted');
    }

    public function activate(StaffService $service): void
    {
        Gate::authorize('activate', $this->staffProfile);
        $service->activateAccount($this->staffProfile, auth()->user());
        $this->refreshProfile();
        Notifier::success('staff.account_activated');
    }

    public function suspend(StaffService $service): void
    {
        Gate::authorize('suspend', $this->staffProfile);
        $service->suspendAccount($this->staffProfile, auth()->user(), 'Suspended from profile');
        $this->refreshProfile();
        Notifier::success('staff.account_suspended');
    }

    public function resetPassword(StaffPasswordService $passwords): void
    {
        Gate::authorize('resetPassword', $this->staffProfile);
        $this->temporaryPassword = $passwords->generateTemporaryPassword();
        $passwords->resetPassword($this->staffProfile->user, $this->temporaryPassword, auth()->user());
        $this->showPasswordModal = true;
        $this->refreshProfile();
        Notifier::success('staff.password_reset');
    }

    private function refreshProfile(): void
    {
        $this->staffProfile = $this->staffProfile->refresh()->load([
            'user.roles.permissions',
            'user.permissions',
            'employmentRecord.jobTitle',
            'employmentRecord.primaryDepartment',
            'educationRecords',
            'professionalLicenses',
            'documents',
            'activeSignature',
            'signatures',
            'emergencyContacts',
        ]);
    }

    public function render(StaffComplianceService $compliance): View
    {
        return view('livewire.staff.show', [
            'completion' => $compliance->getProfileCompletionPercentage($this->staffProfile),
            'warnings' => $compliance->getComplianceWarnings($this->staffProfile),
            'documentTypes' => StaffDocumentType::cases(),
            'loginHistories' => Gate::allows('viewLoginHistory', $this->staffProfile)
                ? $this->staffProfile->user->loginHistories()->latest()->limit(20)->get()
                : collect(),
            'activities' => Gate::allows('viewActivity', $this->staffProfile)
                ? ActivityLog::query()->where('subject_type', StaffProfile::class)->where('subject_id', $this->staffProfile->id)->latest()->limit(20)->get()
                : collect(),
        ])->layout('components.layouts.app', [
            'title' => $this->staffProfile->fullName(),
            'description' => $this->staffProfile->employee_number,
        ]);
    }
}
