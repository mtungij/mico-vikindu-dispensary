<?php

namespace App\Livewire\Facility;

use App\Enums\FacilityDocumentType;
use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Events\FacilityBrandingUpdated;
use App\Events\FacilityCreated;
use App\Events\FacilityDocumentDeleted;
use App\Events\FacilityDocumentUploaded;
use App\Events\FacilityUpdated;
use App\Models\Facility;
use App\Models\FacilityDocument;
use App\Services\FacilityContext;
use App\Services\FacilitySetupService;
use App\Services\PhoneNumberService;
use App\Services\TanzaniaAdministrativeAreas;
use App\Support\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class SetupWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?Facility $facility = null;

    public string $name = '';
    public ?string $code = null;
    public string $facility_type = '';
    public string $ownership_type = '';
    public ?string $registration_number = null;
    public ?string $tin_number = null;
    public string $phone_primary = '';
    public ?string $phone_secondary = null;
    public ?string $email = null;
    public ?string $website = null;

    public string $country = 'Tanzania';
    public string $region = '';
    public string $district = '';
    public ?string $council = null;
    public ?string $ward = null;
    public ?string $street_or_village = null;
    public ?string $physical_address = null;
    public ?string $postal_address = null;
    public string $timezone = 'Africa/Dar_es_Salaam';

    public ?string $operating_license_number = null;
    public ?string $operating_license_expiry_date = null;
    public ?string $nhif_accreditation_number = null;
    public ?string $nhif_contract_number = null;
    public bool $accepts_insurance = false;
    public bool $nhif_enabled = false;
    public bool $other_insurance_enabled = false;
    public bool $license_expired_acknowledged = false;

    public ?TemporaryUploadedFile $logo = null;
    public ?TemporaryUploadedFile $favicon = null;
    public ?TemporaryUploadedFile $official_stamp = null;
    public bool $showDocumentModal = false;
    public string $document_type = 'other';
    public string $document_name = '';
    public ?string $document_number = null;
    public ?string $document_issue_date = null;
    public ?string $document_expiry_date = null;
    public ?TemporaryUploadedFile $document_file = null;
    public ?string $document_notes = null;

    public string $default_language = 'sw';
    public string $fallback_language = 'en';
    public string $currency = 'TZS';
    public string $currency_symbol = 'TSh';
    public string $date_format = 'd/m/Y';
    public string $time_format = 'H:i';
    public string $primary_color = '#0F766E';
    public string $secondary_color = '#14B8A6';
    public ?string $receipt_header = null;
    public ?string $receipt_footer = null;
    public ?string $report_footer = null;
    public bool $enable_dark_mode = true;
    public string $default_theme = 'system';
    public bool $enable_patient_numbers = true;
    public string $patient_number_prefix = 'PAT';
    public bool $enable_receipt_numbers = true;
    public string $receipt_number_prefix = 'RCT';
    public bool $enable_invoice_numbers = true;
    public string $invoice_number_prefix = 'INV';
    public int $fiscal_year_start_month = 7;
    public bool $require_payment_before_service = true;
    public bool $allow_partial_payments = false;
    public bool $enable_audit_logs = true;
    public bool $enable_file_attachments = true;
    public bool $enable_sms_notifications = false;
    public bool $enable_email_notifications = false;
    public bool $enable_whatsapp_notifications = false;

    public function mount(FacilitySetupService $setup): void
    {
        Gate::authorize('setupFacility', Facility::class);

        if ($setup->isSetupCompleted()) {
            $this->redirectRoute('dashboard', navigate: true);
            return;
        }

        $this->facility = $setup->getCurrentFacility();
        $this->fillFromFacility($this->facility, $setup);
        $this->step = max(1, min(6, $this->facility?->setup_current_step ?? 1));
    }

    public function updatedName(FacilitySetupService $setup): void
    {
        if (! $this->code && trim($this->name) !== '') {
            $this->code = $setup->generateUniqueCode($this->name);
        }
    }

    public function updatedRegion(): void
    {
        $this->district = '';
    }

    public function nextStep(FacilitySetupService $setup): void
    {
        $this->saveDraft($setup, false);
        $this->step = min(6, $this->step + 1);
        $this->facility?->forceFill(['setup_current_step' => $this->step])->save();
        $this->dispatch('scroll-to-top');
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->dispatch('scroll-to-top');
    }

    public function goToStep(int $step, FacilitySetupService $setup): void
    {
        if ($step <= $this->step) {
            $this->step = max(1, min(6, $step));
            return;
        }

        $this->saveDraft($setup, false);
        $this->step = min($step, ($this->facility?->setup_current_step ?? 1) + 1);
    }

    public function saveDraft(FacilitySetupService $setup, bool $notify = true): void
    {
        Gate::authorize('setupFacility', Facility::class);
        $this->validateCurrentStep();

        DB::transaction(function () use ($setup): void {
            $payload = $this->facilityPayload();

            if ($this->facility === null) {
                $payload['created_by'] = auth()->id();
                $this->facility = Facility::query()->create($payload);
                event(new FacilityCreated($this->facility));
            } else {
                $oldValues = $this->facility->only(array_keys($payload));
                $this->facility->update($payload);
                event(new FacilityUpdated($this->facility, $oldValues));
            }

            $this->saveSettings($setup, $this->facility);
            $this->storeBrandingFiles($setup, $this->facility);
            $setup->ensureRequiredSettingsExist($this->facility);
        });

        app(FacilityContext::class)->forget();
        $this->facility?->refresh()->load(['settings', 'documents.uploader']);

        if ($notify) {
            Notifier::success('messages.draft_saved');
        }
    }

    public function uploadDocument(): void
    {
        Gate::authorize('uploadFacilityDocument', FacilityDocument::class);
        $this->validate([
            'document_type' => ['required', Rule::enum(FacilityDocumentType::class)],
            'document_name' => ['required', 'string', 'max:150'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_issue_date' => ['nullable', 'date'],
            'document_expiry_date' => ['nullable', 'date'],
            'document_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'document_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->facility === null) {
            $this->saveDraft(app(FacilitySetupService::class), false);
        }

        $path = $this->safeStore($this->document_file, "facilities/{$this->facility->id}/documents", 'local');

        $document = $this->facility->documents()->create([
            'document_type' => $this->document_type,
            'document_name' => $this->document_name,
            'document_number' => $this->document_number,
            'issue_date' => $this->document_issue_date,
            'expiry_date' => $this->document_expiry_date,
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
            'notes' => $this->document_notes,
        ]);

        event(new FacilityDocumentUploaded($document));
        $this->reset(['showDocumentModal', 'document_name', 'document_number', 'document_issue_date', 'document_expiry_date', 'document_file', 'document_notes']);
        $this->document_type = 'other';
        $this->facility->refresh()->load(['settings', 'documents.uploader']);
        Notifier::success('messages.document_uploaded');
    }

    public function deleteDocument(int $documentId): void
    {
        $document = FacilityDocument::query()->findOrFail($documentId);
        Gate::authorize('deleteFacilityDocument', $document);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();
        event(new FacilityDocumentDeleted($document));
        $this->facility?->refresh()->load(['settings', 'documents.uploader']);
        Notifier::success('messages.deleted');
    }

    public function completeSetup(FacilitySetupService $setup): void
    {
        Gate::authorize('completeFacilitySetup', $this->facility ?? Facility::class);

        try {
            $this->saveDraft($setup, false);
            $readiness = $setup->validateSetupReadiness($this->facility->refresh(), auth()->user());

            if ($readiness['blocking'] !== []) {
                $this->addError('completion', implode(' ', $readiness['blocking']));
                Notifier::warning('messages.check_inputs');
                return;
            }

            $setup->markSetupCompleted($this->facility, auth()->user());
            Notifier::success('messages.setup_completed');
            $this->redirectRoute('dashboard', navigate: true);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Facility setup completion failed.', [
                'facility_id' => $this->facility?->id,
                'user_id' => auth()->id(),
                'exception' => $exception::class,
            ]);
            Notifier::error('messages.setup_failed');
        }
    }

    /**
     * @return array<int, string>
     */
    public function districts(): array
    {
        return app(TanzaniaAdministrativeAreas::class)->districtsForRegion($this->region);
    }

    /**
     * @return array{blocking:array<int,string>,warnings:array<int,string>}
     */
    public function readiness(): array
    {
        return $this->facility
            ? app(FacilitySetupService::class)->validateSetupReadiness($this->facility, auth()->user())
            : ['blocking' => ['Facility record not saved'], 'warnings' => []];
    }

    private function validateCurrentStep(): void
    {
        $rules = match ($this->step) {
            1 => [
                'name' => ['required', 'string', 'max:150'],
                'code' => ['nullable', 'alpha_dash', 'max:30', Rule::unique('facilities', 'code')->ignore($this->facility?->id)],
                'facility_type' => ['required', Rule::enum(FacilityType::class)],
                'ownership_type' => ['required', Rule::enum(OwnershipType::class)],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'tin_number' => ['nullable', 'string', 'max:50'],
                'phone_primary' => ['required', fn ($attribute, $value, $fail) => $this->validatePhone($value, $fail)],
                'phone_secondary' => ['nullable', 'different:phone_primary', fn ($attribute, $value, $fail) => $this->validatePhone($value, $fail)],
                'email' => ['nullable', 'email:rfc', 'max:150'],
                'website' => ['nullable', 'url'],
            ],
            2 => [
                'country' => ['required', 'string', 'max:80'],
                'region' => ['required', fn ($attribute, $value, $fail) => $this->validateRegion($value, $fail)],
                'district' => ['required', fn ($attribute, $value, $fail) => $this->validateDistrict($value, $fail)],
                'ward' => ['required', 'string', 'max:100'],
                'physical_address' => ['required', 'string', 'max:500'],
                'timezone' => ['required', 'timezone'],
            ],
            3 => [
                'operating_license_number' => ['nullable', 'string', 'max:100'],
                'operating_license_expiry_date' => ['nullable', 'date'],
                'nhif_accreditation_number' => [Rule::requiredIf($this->nhif_enabled), 'nullable', 'string', 'max:100'],
                'nhif_contract_number' => ['nullable', 'string', 'max:100'],
            ],
            4 => [
                'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg', 'max:1024'],
                'official_stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            5, 6 => [
                'default_language' => ['required', 'in:sw,en'],
                'fallback_language' => ['required', 'in:sw,en'],
                'currency' => ['required', 'string', 'max:10'],
                'currency_symbol' => ['required', 'string', 'max:20'],
                'timezone' => ['required', 'timezone'],
                'date_format' => ['required', 'string', 'max:30'],
                'time_format' => ['required', 'string', 'max:30'],
                'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ],
            default => [],
        };

        $this->validate($rules);

        if ($this->step === 3 && $this->operating_license_expiry_date && now()->toDateString() > $this->operating_license_expiry_date && ! $this->license_expired_acknowledged) {
            throw ValidationException::withMessages([
                'operating_license_expiry_date' => 'Leseni imeisha muda. Thibitisha warning ili uendelee.',
            ]);
        }
    }

    private function facilityPayload(): array
    {
        $phone = app(PhoneNumberService::class);

        return [
            'name' => $this->name,
            'code' => $this->code ? strtoupper($this->code) : app(FacilitySetupService::class)->generateUniqueCode($this->name),
            'facility_type' => $this->facility_type,
            'ownership_type' => $this->ownership_type,
            'registration_number' => $this->registration_number,
            'tin_number' => $this->tin_number,
            'phone_primary' => $phone->normalize($this->phone_primary),
            'phone_secondary' => $phone->normalize($this->phone_secondary),
            'email' => $this->email,
            'website' => $this->website,
            'country' => $this->country,
            'region' => $this->region,
            'district' => $this->district,
            'council' => $this->council,
            'ward' => $this->ward,
            'street_or_village' => $this->street_or_village,
            'physical_address' => $this->physical_address,
            'postal_address' => $this->postal_address,
            'timezone' => $this->timezone,
            'operating_license_number' => $this->operating_license_number,
            'operating_license_expiry_date' => $this->operating_license_expiry_date,
            'nhif_accreditation_number' => $this->nhif_accreditation_number,
            'nhif_contract_number' => $this->nhif_contract_number,
            'default_language' => $this->default_language,
            'fallback_language' => $this->fallback_language,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'receipt_header' => $this->receipt_header,
            'receipt_footer' => $this->receipt_footer,
            'report_footer' => $this->report_footer,
            'setup_current_step' => max($this->facility?->setup_current_step ?? 1, min(6, $this->step)),
            'updated_by' => auth()->id(),
        ];
    }

    private function saveSettings(FacilitySetupService $setup, Facility $facility): void
    {
        foreach ([
            'accepts_insurance' => [$this->accepts_insurance, 'boolean', 'insurance'],
            'nhif_enabled' => [$this->nhif_enabled, 'boolean', 'insurance'],
            'other_insurance_enabled' => [$this->other_insurance_enabled, 'boolean', 'insurance'],
            'enable_dark_mode' => [$this->enable_dark_mode, 'boolean', 'system'],
            'default_theme' => [$this->default_theme, 'string', 'system'],
            'enable_patient_numbers' => [$this->enable_patient_numbers, 'boolean', 'numbering'],
            'patient_number_prefix' => [$this->patient_number_prefix, 'string', 'numbering'],
            'enable_receipt_numbers' => [$this->enable_receipt_numbers, 'boolean', 'numbering'],
            'receipt_number_prefix' => [$this->receipt_number_prefix, 'string', 'numbering'],
            'enable_invoice_numbers' => [$this->enable_invoice_numbers, 'boolean', 'numbering'],
            'invoice_number_prefix' => [$this->invoice_number_prefix, 'string', 'numbering'],
            'fiscal_year_start_month' => [$this->fiscal_year_start_month, 'integer', 'finance'],
            'require_payment_before_service' => [$this->require_payment_before_service, 'boolean', 'finance'],
            'allow_partial_payments' => [$this->allow_partial_payments, 'boolean', 'finance'],
            'enable_audit_logs' => [$this->enable_audit_logs, 'boolean', 'system'],
            'enable_file_attachments' => [$this->enable_file_attachments, 'boolean', 'system'],
            'enable_sms_notifications' => [$this->enable_sms_notifications, 'boolean', 'notifications'],
            'enable_email_notifications' => [$this->enable_email_notifications, 'boolean', 'notifications'],
            'enable_whatsapp_notifications' => [$this->enable_whatsapp_notifications, 'boolean', 'notifications'],
        ] as $key => [$value, $type, $group]) {
            $setup->saveSetting($facility, $key, $value, $type, $group);
        }
    }

    private function storeBrandingFiles(FacilitySetupService $setup, Facility $facility): void
    {
        $changed = false;

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path', 'official_stamp' => 'official_stamp_path'] as $property => $column) {
            if ($this->{$property} instanceof TemporaryUploadedFile) {
                $setup->deleteOldFileSafely($facility->{$column}, 'public');
                $facility->{$column} = $this->safeStore($this->{$property}, "facilities/{$facility->id}/branding", 'public');
                $changed = true;
            }
        }

        if ($changed) {
            $facility->save();
            event(new FacilityBrandingUpdated($facility));
            $this->reset(['logo', 'favicon', 'official_stamp']);
        }
    }

    private function safeStore(TemporaryUploadedFile $file, string $directory, string $disk): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = str()->uuid()->toString().'.'.$extension;

        return $file->storeAs($directory, $filename, $disk);
    }

    private function fillFromFacility(?Facility $facility, FacilitySetupService $setup): void
    {
        if ($facility === null) {
            return;
        }

        $facility->loadMissing(['settings', 'documents.uploader']);
        foreach ($facility->only([
            'name', 'code', 'registration_number', 'tin_number', 'phone_primary', 'phone_secondary',
            'email', 'website', 'country', 'region', 'district', 'council', 'ward', 'street_or_village',
            'physical_address', 'postal_address', 'timezone', 'operating_license_number',
            'nhif_accreditation_number', 'nhif_contract_number', 'default_language', 'fallback_language',
            'currency', 'currency_symbol', 'date_format', 'time_format', 'primary_color', 'secondary_color',
            'receipt_header', 'receipt_footer', 'report_footer',
        ]) as $key => $value) {
            $this->{$key} = $value;
        }

        $this->facility_type = $facility->facility_type->value;
        $this->ownership_type = $facility->ownership_type->value;
        $this->operating_license_expiry_date = $facility->operating_license_expiry_date?->toDateString();

        foreach (config('facility.settings', []) as $key => $definition) {
            $this->{$key} = $setup->getSetting($facility, $key, $definition['value']);
        }
    }

    private function validatePhone(?string $value, callable $fail): void
    {
        if ($value && ! app(PhoneNumberService::class)->isValid($value)) {
            $fail('Namba ya simu si sahihi.');
        }
    }

    private function validateRegion(?string $value, callable $fail): void
    {
        if (! $value || ! app(TanzaniaAdministrativeAreas::class)->isValidRegion($value)) {
            $fail('Mkoa uliochaguliwa si sahihi.');
        }
    }

    private function validateDistrict(?string $value, callable $fail): void
    {
        if (! $value || ! app(TanzaniaAdministrativeAreas::class)->isValidDistrict($this->region, $value)) {
            $fail('Wilaya haiendani na mkoa uliochaguliwa.');
        }
    }

    public function render(FacilitySetupService $setup): View
    {
        return view('livewire.facility.setup-wizard', [
            'facilityTypes' => FacilityType::cases(),
            'ownershipTypes' => OwnershipType::cases(),
            'documentTypes' => FacilityDocumentType::cases(),
            'regions' => app(TanzaniaAdministrativeAreas::class)->regions(),
            'progress' => $this->facility ? $setup->getSetupProgress($this->facility) : ['percentage' => 0],
        ])->layout('components.layouts.app', [
            'title' => 'Facility Setup',
            'description' => 'Kamilisha taarifa za kituo kabla ya kutumia mfumo.',
        ]);
    }
}
