<?php

namespace App\Livewire\Dental\Settings;

use App\Models\DentalAnaestheticType;
use App\Models\DentalAppointmentType;
use App\Models\DentalChair;
use App\Models\DentalConsentTemplate;
use App\Models\DentalProcedureTemplate;
use App\Models\DentalProcedureType;
use App\Models\DentalRoom;
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

    public string $section;
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
        $model = $this->editingId ? $this->query()->findOrFail($this->editingId) : new ($this->modelClass());
        $data['code'] = str($data['code'])->upper()->replace(' ', '_')->toString();
        if ($this->usesFacilityId()) $data['facility_id'] = currentFacility()?->id;
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
        $model->update(['is_active' => ! $model->is_active]);
        Notifier::success('messages.updated');
    }

    public function render()
    {
        return view('livewire.dental.settings.manage-catalog', [
            'rows' => $this->query()->when($this->search, fn (Builder $q) => $q->where(fn ($qq) => $qq->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')))->latest()->paginate(12),
            'title' => $this->title(),
            'fields' => $this->fields(),
            'procedureTypes' => DentalProcedureType::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'services' => Service::query()->forCurrentFacility()->whereIn('service_type', ['dental_service','procedure','consultation'])->orderBy('name')->get(),
            'anaesthetics' => DentalAnaestheticType::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'consentTemplates' => DentalConsentTemplate::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
            'rooms' => DentalRoom::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => $this->title(), 'description' => 'Dental setup.']);
    }

    protected function query(): Builder
    {
        return ($this->modelClass())::query()->forCurrentFacility();
    }

    protected function rules(): array
    {
        $table = (new ($this->modelClass()))->getTable();
        $facilityId = $this->usesFacilityId() ? currentFacility()?->id : null;
        $rules = [];
        foreach ($this->fields() as $field => $meta) {
            $rules["form.$field"] = $meta['rules'] ?? ['nullable'];
        }
        $rules['form.code'] = ['required','string','max:50', Rule::unique($table, 'code')->where('facility_id', $facilityId)->ignore($this->editingId)];
        return $rules;
    }

    protected function resetForm(): void
    {
        $this->form = $this->defaults();
    }

    protected function modelClass(): string
    {
        return match ($this->section) {
            'procedure-types' => DentalProcedureType::class,
            'procedure-templates' => DentalProcedureTemplate::class,
            'anaesthetics' => DentalAnaestheticType::class,
            'consents' => DentalConsentTemplate::class,
            'appointment-types' => DentalAppointmentType::class,
            'rooms' => DentalRoom::class,
            'chairs' => DentalChair::class,
        };
    }

    protected function permission(): string
    {
        return match ($this->section) {
            'rooms' => 'dental.manage-rooms',
            'chairs' => 'dental.manage-chairs',
            'anaesthetics' => 'dental.manage-anaesthetics',
            'procedure-templates' => 'dental.manage-procedure-templates',
            default => 'dental.manage-settings',
        };
    }

    protected function usesFacilityId(): bool
    {
        return true;
    }

    protected function title(): string
    {
        return str($this->section)->replace('-', ' ')->title()->prepend('Dental ')->toString();
    }

    protected function defaults(): array
    {
        return match ($this->section) {
            'procedure-types' => ['name'=>'','code'=>'','category'=>'preventive','description'=>'','requires_tooth'=>false,'requires_surface'=>false,'requires_consent'=>false,'requires_payment'=>true,'updates_odontogram'=>true,'can_require_observation'=>false,'is_active'=>true,'sort_order'=>0],
            'procedure-templates' => ['name'=>'','code'=>'','dental_procedure_type_id'=>null,'service_id'=>null,'default_diagnosis'=>'','default_anaesthesia_type'=>'','default_anaesthetic_id'=>null,'requires_consent'=>false,'consent_template_id'=>null,'default_post_op_instructions'=>'','default_follow_up_days'=>null,'send_to_observation'=>false,'is_active'=>true],
            'anaesthetics' => ['name'=>'','code'=>'','generic_name'=>'','concentration'=>'','route'=>'injection','maximum_dose_note'=>'','warnings'=>'','is_active'=>true],
            'consents' => ['name'=>'','code'=>'','consent_type'=>'general_treatment','content'=>'','risks'=>'','alternatives'=>'','is_active'=>true],
            'appointment-types' => ['name'=>'','code'=>'','default_duration_minutes'=>30,'description'=>'','is_active'=>true,'sort_order'=>0],
            'rooms' => ['name'=>'','code'=>'','location'=>'','is_active'=>true,'notes'=>''],
            'chairs' => ['name'=>'','code'=>'','dental_room_id'=>null,'status'=>'available','assigned_provider_id'=>null,'is_active'=>true,'notes'=>''],
        };
    }

    protected function fields(): array
    {
        return match ($this->section) {
            'procedure-types' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'category'=>['type'=>'select','options'=>['preventive','restorative','endodontic','periodontal','orthodontic','oral_surgery','cosmetic','prosthodontic','diagnostic','other'],'rules'=>['required','string']],'description'=>['type'=>'textarea'],'requires_tooth'=>['type'=>'checkbox'],'requires_surface'=>['type'=>'checkbox'],'requires_consent'=>['type'=>'checkbox'],'requires_payment'=>['type'=>'checkbox'],'updates_odontogram'=>['type'=>'checkbox'],'can_require_observation'=>['type'=>'checkbox'],'is_active'=>['type'=>'checkbox']],
            'procedure-templates' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'dental_procedure_type_id'=>['type'=>'procedure_type','rules'=>['required','integer']],'service_id'=>['type'=>'service'],'default_diagnosis'=>['type'=>'text'],'default_anaesthesia_type'=>['type'=>'text'],'default_anaesthetic_id'=>['type'=>'anaesthetic'],'requires_consent'=>['type'=>'checkbox'],'consent_template_id'=>['type'=>'consent_template'],'default_post_op_instructions'=>['type'=>'textarea'],'default_follow_up_days'=>['type'=>'number'],'send_to_observation'=>['type'=>'checkbox'],'is_active'=>['type'=>'checkbox']],
            'anaesthetics' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'generic_name'=>['type'=>'text'],'concentration'=>['type'=>'text'],'route'=>['type'=>'text'],'maximum_dose_note'=>['type'=>'textarea'],'warnings'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'consents' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'consent_type'=>['type'=>'select','options'=>['extraction','surgical_extraction','root_canal','orthodontics','cosmetic','local_anaesthesia','photography','general_treatment','other']],'content'=>['type'=>'textarea','rules'=>['required','string']],'risks'=>['type'=>'textarea'],'alternatives'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'appointment-types' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'default_duration_minutes'=>['type'=>'number'],'description'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'rooms' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'location'=>['type'=>'text'],'notes'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
            'chairs' => ['name'=>['type'=>'text','rules'=>['required','string','max:120']],'code'=>['type'=>'text'],'dental_room_id'=>['type'=>'room','rules'=>['required','integer']],'status'=>['type'=>'select','options'=>['available','occupied','cleaning','maintenance','out_of_service']],'notes'=>['type'=>'textarea'],'is_active'=>['type'=>'checkbox']],
        };
    }
}
