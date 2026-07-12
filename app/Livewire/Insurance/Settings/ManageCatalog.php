<?php

namespace App\Livewire\Insurance\Settings;

use App\Models\InsuranceBenefitPackage;
use App\Models\InsuranceClaimRejectionReason;
use App\Models\InsuranceClaimRule;
use App\Models\InsuranceContractPrice;
use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceMedicineCodeMapping;
use App\Models\InsuranceMembershipPlan;
use App\Models\InsuranceProvider;
use App\Models\InsuranceScheme;
use App\Models\InsuranceServiceCodeMapping;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toaster as Notifier;

abstract class ManageCatalog extends Component
{
    use WithPagination;

    public string $section = '';
    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize($this->permission());
        $this->resetForm();
    }

    public function create(): void
    {
        Gate::authorize($this->permission());
        $this->editingId = null;
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize($this->permission());
        $model = $this->query()->findOrFail($id);
        $this->editingId = $model->id;
        $this->form = collect($model->getAttributes())->only(array_keys($this->defaults()))->all();
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize($this->permission());
        $data = $this->validate($this->rules())['form'];
        if (isset($data['code'])) $data['code'] = str($data['code'])->upper()->replace(' ', '_')->toString();
        $model = $this->editingId ? $this->query()->findOrFail($this->editingId) : new ($this->modelClass());
        if (in_array('facility_id', $model->getFillable(), true)) $data['facility_id'] = currentFacility()?->id;
        if (in_array('created_by', $model->getFillable(), true) && ! $model->exists) $data['created_by'] = auth()->id();
        if (in_array('updated_by', $model->getFillable(), true)) $data['updated_by'] = auth()->id();
        $model->fill($data)->save();
        $this->showModal = false;
        $this->resetForm();
        Notifier::success('messages.saved');
    }

    public function toggle(int $id): void
    {
        Gate::authorize($this->permission());
        $model = $this->query()->findOrFail($id);
        if (array_key_exists('is_active', $model->getAttributes())) $model->update(['is_active' => ! $model->is_active]);
        Notifier::success('messages.updated');
    }

    public function render()
    {
        return view('livewire.insurance.settings.manage-catalog', [
            'rows' => $this->query()->when($this->search, fn (Builder $q) => $q->where(fn ($qq) => $qq->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')))->latest()->paginate(12),
            'title' => $this->title(),
            'fields' => $this->fields(),
            'providers' => InsuranceProvider::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'schemes' => InsuranceScheme::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'packages' => InsuranceBenefitPackage::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'services' => Service::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => $this->title(), 'description' => 'Mipangilio ya bima na madai.']);
    }

    protected function query(): Builder
    {
        return ($this->modelClass())::query()->forCurrentFacility();
    }

    protected function rules(): array
    {
        $rules = [];
        foreach ($this->fields() as $field => $meta) $rules["form.$field"] = $meta['rules'] ?? ['nullable'];
        if (array_key_exists('code', $this->defaults())) {
            $model = new ($this->modelClass());
            $unique = Rule::unique($model->getTable(), 'code')->ignore($this->editingId);
            if (array_key_exists('insurance_provider_id', $this->defaults())) $unique = $unique->where('insurance_provider_id', $this->form['insurance_provider_id'] ?? null);
            elseif (array_key_exists('facility_id', $model->getFillable())) $unique = $unique->where('facility_id', currentFacility()?->id);
            $rules['form.code'] = ['required', 'string', 'max:50', $unique];
        }

        return $rules;
    }

    protected function resetForm(): void { $this->form = $this->defaults(); }

    protected function modelClass(): string
    {
        return match ($this->section) {
            'providers' => InsuranceProvider::class,
            'schemes' => InsuranceScheme::class,
            'benefit-packages' => InsuranceBenefitPackage::class,
            'membership-plans' => InsuranceMembershipPlan::class,
            'coverage-rules', 'service-coverage', 'medicine-coverage' => InsuranceCoverageRule::class,
            'contract-prices' => InsuranceContractPrice::class,
            'service-codes', 'procedure-codes' => InsuranceServiceCodeMapping::class,
            'medicine-codes' => InsuranceMedicineCodeMapping::class,
            'claim-rules' => InsuranceClaimRule::class,
            'rejection-reasons' => InsuranceClaimRejectionReason::class,
            default => InsuranceProvider::class,
        };
    }

    protected function permission(): string
    {
        return match ($this->section) {
            'providers' => 'insurance.manage-providers',
            'schemes' => 'insurance.manage-schemes',
            'benefit-packages' => 'insurance.manage-benefit-packages',
            'membership-plans' => 'insurance.manage-membership-plans',
            'coverage-rules', 'service-coverage', 'medicine-coverage' => 'insurance.manage-coverage',
            'contract-prices' => 'insurance.manage-contract-prices',
            'service-codes', 'procedure-codes' => 'insurance.manage-service-codes',
            'medicine-codes' => 'insurance.manage-medicine-codes',
            'claim-rules', 'rejection-reasons' => 'insurance.manage-claim-rules',
            default => 'insurance.manage-settings',
        };
    }

    protected function title(): string
    {
        return 'Insurance '.str($this->section)->replace('-', ' ')->title();
    }

    protected function defaults(): array
    {
        return match ($this->section) {
            'providers' => ['name'=>'','code'=>'','provider_type'=>'private_insurance','claim_submission_method'=>'manual_report','payment_terms_days'=>30,'default_currency'=>'TZS','requires_pre_authorization'=>false,'requires_referral'=>false,'supports_dependants'=>true,'supports_copayment'=>true,'supports_partial_approval'=>true,'claim_prefix'=>'','notes'=>'','is_active'=>true],
            'schemes' => ['insurance_provider_id'=>null,'name'=>'','code'=>'','scheme_type'=>'individual','description'=>'','requires_membership_verification'=>true,'requires_pre_authorization'=>false,'requires_referral'=>false,'allows_dependants'=>true,'allows_copayment'=>true,'is_active'=>true],
            'benefit-packages' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'name'=>'','code'=>'','description'=>'','annual_limit'=>null,'visit_limit'=>null,'dental_limit'=>null,'pharmacy_limit'=>null,'laboratory_limit'=>null,'observation_limit'=>null,'is_active'=>true],
            'membership-plans' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'benefit_package_id'=>null,'name'=>'','code'=>'','membership_type'=>'principal','waiting_period_days'=>0,'dependent_limit'=>null,'copayment_type'=>null,'copayment_value'=>null,'coinsurance_percentage'=>null,'deductible_amount'=>null,'is_active'=>true],
            'coverage-rules', 'service-coverage' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'benefit_package_id'=>null,'rule_scope'=>'service','service_id'=>null,'coverage_status'=>'covered','coverage_percentage'=>100,'patient_copayment_type'=>null,'patient_copayment_value'=>null,'requires_pre_authorization'=>false,'requires_referral'=>false,'priority'=>0,'notes'=>'','is_active'=>true],
            'medicine-coverage' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'benefit_package_id'=>null,'rule_scope'=>'medicine','medicine_id'=>null,'coverage_status'=>'covered','coverage_percentage'=>100,'requires_pre_authorization'=>false,'requires_referral'=>false,'priority'=>0,'notes'=>'','is_active'=>true],
            'contract-prices' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'benefit_package_id'=>null,'service_id'=>null,'price'=>0,'patient_amount'=>null,'payer_amount'=>null,'effective_from'=>today()->toDateString(),'authorization_required'=>false,'notes'=>'','is_active'=>true],
            'service-codes', 'procedure-codes' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'service_id'=>null,'payer_service_code'=>'','payer_service_name'=>'','procedure_code'=>'','is_active'=>true,'notes'=>''],
            'medicine-codes' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'medicine_id'=>null,'payer_medicine_code'=>'','payer_medicine_name'=>'','maximum_quantity'=>null,'is_active'=>true,'notes'=>''],
            'claim-rules' => ['insurance_provider_id'=>null,'insurance_scheme_id'=>null,'claim_submission_days'=>30,'correction_submission_days'=>14,'resubmission_days'=>14,'requires_primary_diagnosis'=>true,'requires_service_codes'=>true,'requires_provider_signature'=>false,'requires_facility_stamp'=>false,'requires_invoice_attachment'=>false,'is_active'=>true],
            'rejection-reasons' => ['insurance_provider_id'=>null,'code'=>'','name'=>'','category'=>'other','description'=>'','correction_action'=>'','is_active'=>true,'sort_order'=>0],
            default => [],
        };
    }

    protected function fields(): array
    {
        $base = [
            'provider' => ['insurance_provider_id'=>['type'=>'provider','rules'=>['nullable','integer']]],
            'scheme' => ['insurance_scheme_id'=>['type'=>'scheme','rules'=>['nullable','integer']]],
            'package' => ['benefit_package_id'=>['type'=>'package','rules'=>['nullable','integer']]],
        ];

        return match ($this->section) {
            'providers' => ['name'=>['type'=>'text','rules'=>['required','max:150']],'code'=>['type'=>'text'],'provider_type'=>['type'=>'select','options'=>['national_health_insurance','private_insurance','corporate_insurance','community_health_fund','employer_scheme','government_scheme','other']],'claim_submission_method'=>['type'=>'select','options'=>['manual_report','portal_upload','email','physical_document','api_future','other']],'payment_terms_days'=>['type'=>'number'],'default_currency'=>['type'=>'text'],'requires_pre_authorization'=>['type'=>'checkbox'],'requires_referral'=>['type'=>'checkbox'],'supports_dependants'=>['type'=>'checkbox'],'supports_copayment'=>['type'=>'checkbox'],'supports_partial_approval'=>['type'=>'checkbox'],'claim_prefix'=>['type'=>'text'],'notes'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'schemes' => $base['provider'] + ['name'=>['type'=>'text','rules'=>['required','max:150']],'code'=>['type'=>'text'],'scheme_type'=>['type'=>'select','options'=>['individual','family','employer','corporate','government','student','pensioner','community','other']],'description'=>['type'=>'textarea'],'requires_membership_verification'=>['type'=>'checkbox'],'requires_pre_authorization'=>['type'=>'checkbox'],'requires_referral'=>['type'=>'checkbox'],'allows_dependants'=>['type'=>'checkbox'],'allows_copayment'=>['type'=>'checkbox'],'is_active'=>['type'=>'checkbox']],
            'benefit-packages' => $base['provider'] + $base['scheme'] + ['name'=>['type'=>'text','rules'=>['required','max:150']],'code'=>['type'=>'text'],'description'=>['type'=>'textarea'],'annual_limit'=>['type'=>'number'],'visit_limit'=>['type'=>'number'],'dental_limit'=>['type'=>'number'],'pharmacy_limit'=>['type'=>'number'],'laboratory_limit'=>['type'=>'number'],'observation_limit'=>['type'=>'number'],'is_active'=>['type'=>'checkbox']],
            'membership-plans' => $base['provider'] + $base['scheme'] + $base['package'] + ['name'=>['type'=>'text','rules'=>['required','max:150']],'code'=>['type'=>'text'],'membership_type'=>['type'=>'select','options'=>['principal','dependant','family','employee','student','pensioner','other']],'waiting_period_days'=>['type'=>'number'],'dependent_limit'=>['type'=>'number'],'copayment_type'=>['type'=>'select','options'=>['','fixed','percentage']],'copayment_value'=>['type'=>'number'],'coinsurance_percentage'=>['type'=>'number'],'deductible_amount'=>['type'=>'number'],'is_active'=>['type'=>'checkbox']],
            'coverage-rules', 'service-coverage', 'medicine-coverage' => $base['provider'] + $base['scheme'] + $base['package'] + ['rule_scope'=>['type'=>'select','options'=>['service','service_category','medicine','department','diagnosis','benefit_type','all']],'service_id'=>['type'=>'service'],'coverage_status'=>['type'=>'select','options'=>['covered','partially_covered','excluded','authorization_required','referral_required','limit_exceeded','not_configured']],'coverage_percentage'=>['type'=>'number'],'patient_copayment_type'=>['type'=>'select','options'=>['','fixed','percentage']],'patient_copayment_value'=>['type'=>'number'],'requires_pre_authorization'=>['type'=>'checkbox'],'requires_referral'=>['type'=>'checkbox'],'priority'=>['type'=>'number'],'notes'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'contract-prices' => $base['provider'] + $base['scheme'] + $base['package'] + ['service_id'=>['type'=>'service','rules'=>['required','integer']],'price'=>['type'=>'number','rules'=>['required','numeric','min:0']],'patient_amount'=>['type'=>'number'],'payer_amount'=>['type'=>'number'],'effective_from'=>['type'=>'date','rules'=>['required','date']],'authorization_required'=>['type'=>'checkbox'],'notes'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'service-codes', 'procedure-codes' => $base['provider'] + $base['scheme'] + ['service_id'=>['type'=>'service','rules'=>['required','integer']],'payer_service_code'=>['type'=>'text','rules'=>['required','max:100']],'payer_service_name'=>['type'=>'text'],'procedure_code'=>['type'=>'text'],'is_active'=>['type'=>'checkbox'],'notes'=>['type'=>'textarea']],
            'medicine-codes' => $base['provider'] + $base['scheme'] + ['medicine_id'=>['type'=>'number','rules'=>['required','integer']],'payer_medicine_code'=>['type'=>'text','rules'=>['required','max:100']],'payer_medicine_name'=>['type'=>'text'],'maximum_quantity'=>['type'=>'number'],'is_active'=>['type'=>'checkbox'],'notes'=>['type'=>'textarea']],
            'claim-rules' => $base['provider'] + $base['scheme'] + ['claim_submission_days'=>['type'=>'number'],'correction_submission_days'=>['type'=>'number'],'resubmission_days'=>['type'=>'number'],'requires_primary_diagnosis'=>['type'=>'checkbox'],'requires_service_codes'=>['type'=>'checkbox'],'requires_provider_signature'=>['type'=>'checkbox'],'requires_facility_stamp'=>['type'=>'checkbox'],'requires_invoice_attachment'=>['type'=>'checkbox'],'is_active'=>['type'=>'checkbox']],
            'rejection-reasons' => $base['provider'] + ['code'=>['type'=>'text'],'name'=>['type'=>'text','rules'=>['required','max:150']],'category'=>['type'=>'select','options'=>['eligibility','coding','authorization','documentation','pricing','duplicate','exclusion','limit','clinical','administrative','other']],'description'=>['type'=>'textarea'],'correction_action'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox'],'sort_order'=>['type'=>'number']],
            default => [],
        };
    }
}
