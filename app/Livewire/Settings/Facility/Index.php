<?php

namespace App\Livewire\Settings\Facility;

use App\Events\FacilityBrandingUpdated;
use App\Events\FacilityUpdated;
use App\Models\Facility;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Services\FacilityContext;
use App\Services\FacilitySetupService;
use App\Services\PhoneNumberService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public Facility $facility;

    public string $tab = 'basic';

    public bool $showBasicModal = false;

    public string $name = '';
    public ?string $code = null;
    public string $phone_primary = '';
    public ?string $email = null;
    public ?string $registration_number = null;
    public ?string $tin_number = null;
    public ?string $nhif_accreditation_number = null;
    public ?string $receipt_footer = null;
    public bool $charge_new_patient_registration = true;
    public bool $charge_returning_patient_registration = false;
    public ?int $new_patient_registration_service_id = null;
    public ?int $returning_patient_registration_service_id = null;
    public ?int $patient_card_replacement_service_id = null;
    public bool $require_consultation_service = true;
    public bool $auto_add_registration_fee = true;
    public bool $auto_add_consultation_fee = true;

    public ?TemporaryUploadedFile $logo = null;
    public ?TemporaryUploadedFile $official_stamp = null;

    public function mount(FacilitySetupService $setup): void
    {
        $facility = $setup->getCurrentFacility();
        abort_if($facility === null, 404);
        Gate::authorize('updateFacility', $facility);
        $this->facility = $facility->load(['settings', 'documents.uploader']);
        $this->fillForm();
    }

    public function editBasic(): void
    {
        $this->fillForm();
        $this->showBasicModal = true;
    }

    public function saveBasic(PhoneNumberService $phone): void
    {
        Gate::authorize('updateFacility', $this->facility);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'alpha_dash', 'max:30', Rule::unique('facilities', 'code')->ignore($this->facility->id)],
            'phone_primary' => ['required', fn ($attribute, $value, $fail) => $phone->isValid($value) ?: $fail('Namba ya simu si sahihi.')],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'nhif_accreditation_number' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldValues = $this->facility->only(array_keys($validated));
        $validated['phone_primary'] = $phone->normalize($validated['phone_primary']);
        $validated['updated_by'] = auth()->id();
        $this->facility->update($validated);
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'event' => 'facility.updated',
            'subject_type' => Facility::class,
            'subject_id' => $this->facility->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        event(new FacilityUpdated($this->facility, $oldValues));
        app(FacilityContext::class)->forget();
        $this->facility = $this->facility->refresh()->load(['settings', 'documents.uploader']);
        $this->showBasicModal = false;
        Notifier::success('messages.updated');
    }

    public function saveReceptionBilling(FacilitySetupService $setup): void
    {
        Gate::authorize('updateFacility', $this->facility);

        $validated = $this->validate([
            'charge_new_patient_registration' => ['boolean'],
            'charge_returning_patient_registration' => ['boolean'],
            'new_patient_registration_service_id' => ['nullable', 'required_if:charge_new_patient_registration,true', Rule::exists('services', 'id')->where('facility_id', $this->facility->id)->where('service_type', 'registration')],
            'returning_patient_registration_service_id' => ['nullable', 'required_if:charge_returning_patient_registration,true', Rule::exists('services', 'id')->where('facility_id', $this->facility->id)->where('service_type', 'registration')],
            'patient_card_replacement_service_id' => ['nullable', Rule::exists('services', 'id')->where('facility_id', $this->facility->id)],
            'require_consultation_service' => ['boolean'],
            'auto_add_registration_fee' => ['boolean'],
            'auto_add_consultation_fee' => ['boolean'],
        ]);

        foreach ($validated as $key => $value) {
            $setup->saveSetting($this->facility, $key, $value ?? '', is_bool($value) ? 'boolean' : 'string', 'reception_billing');
        }

        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'event' => 'facility.reception_billing_settings_updated',
            'subject_type' => Facility::class,
            'subject_id' => $this->facility->id,
            'new_values' => $validated,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        app(FacilityContext::class)->forget();
        $this->facility = $this->facility->refresh()->load(['settings', 'documents.uploader']);
        Notifier::success('messages.updated');
    }

    public function updateBranding(FacilitySetupService $setup): void
    {
        Gate::authorize('updateFacility', $this->facility);
        $this->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'official_stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        foreach (['logo' => 'logo_path', 'official_stamp' => 'official_stamp_path'] as $property => $column) {
            if ($this->{$property} instanceof TemporaryUploadedFile) {
                $setup->deleteOldFileSafely($this->facility->{$column}, 'public');
                $this->facility->{$column} = $this->{$property}->storeAs(
                    "facilities/{$this->facility->id}/branding",
                    str()->uuid()->toString().'.'.strtolower($this->{$property}->getClientOriginalExtension()),
                    'public'
                );
            }
        }

        $this->facility->save();
        event(new FacilityBrandingUpdated($this->facility));
        app(FacilityContext::class)->forget();
        $this->reset(['logo', 'official_stamp']);
        $this->facility = $this->facility->refresh()->load(['settings', 'documents.uploader']);
        Notifier::success('messages.updated');
    }

    private function fillForm(): void
    {
        $this->name = $this->facility->name;
        $this->code = $this->facility->code;
        $this->phone_primary = $this->facility->phone_primary;
        $this->email = $this->facility->email;
        $this->registration_number = $this->facility->registration_number;
        $this->tin_number = $this->facility->tin_number;
        $this->nhif_accreditation_number = $this->facility->nhif_accreditation_number;
        $this->receipt_footer = $this->facility->receipt_footer;
        $settings = app(FacilitySetupService::class);
        $this->charge_new_patient_registration = (bool) $settings->getSetting($this->facility, 'charge_new_patient_registration', true);
        $this->charge_returning_patient_registration = (bool) $settings->getSetting($this->facility, 'charge_returning_patient_registration', false);
        $this->new_patient_registration_service_id = filled($settings->getSetting($this->facility, 'new_patient_registration_service_id')) ? (int) $settings->getSetting($this->facility, 'new_patient_registration_service_id') : null;
        $this->returning_patient_registration_service_id = filled($settings->getSetting($this->facility, 'returning_patient_registration_service_id')) ? (int) $settings->getSetting($this->facility, 'returning_patient_registration_service_id') : null;
        $this->patient_card_replacement_service_id = filled($settings->getSetting($this->facility, 'patient_card_replacement_service_id')) ? (int) $settings->getSetting($this->facility, 'patient_card_replacement_service_id') : null;
        $this->require_consultation_service = (bool) $settings->getSetting($this->facility, 'require_consultation_service', true);
        $this->auto_add_registration_fee = (bool) $settings->getSetting($this->facility, 'auto_add_registration_fee', true);
        $this->auto_add_consultation_fee = (bool) $settings->getSetting($this->facility, 'auto_add_consultation_fee', true);
    }

    public function render(): View
    {
        return view('livewire.settings.facility.index', [
            'registrationServices' => Service::query()->where('facility_id', $this->facility->id)->where('service_type', 'registration')->where('is_active', true)->orderBy('name')->get(),
            'administrativeServices' => Service::query()->where('facility_id', $this->facility->id)->whereIn('service_type', ['administrative_service', 'registration'])->where('is_active', true)->orderBy('name')->get(),
        ])
            ->layout('components.layouts.app', [
                'title' => 'Facility Settings',
                'description' => 'Dhibiti taarifa na nyaraka za kituo.',
            ]);
    }
}
