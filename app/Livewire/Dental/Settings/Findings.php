<?php

namespace App\Livewire\Dental\Settings;

use App\Models\DentalFindingType;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Findings extends Component
{
    public bool $showModal=false; public string $code=''; public string $name=''; public string $category='caries'; public ?string $color=null; public ?string $icon=null; public bool $applies_to_surface=false; public bool $applies_to_whole_tooth=true; public bool $severity_enabled=false; public bool $is_active=true;
    public function mount(): void { Gate::authorize('dental.manage-odontogram'); }
    public function create(): void { $this->reset(['code','name','category','color','icon','applies_to_surface','severity_enabled']); $this->category='caries'; $this->applies_to_whole_tooth=true; $this->is_active=true; $this->showModal=true; }
    public function save(): void
    {
        Gate::authorize('dental.manage-odontogram');
        $data=$this->validate(['code'=>['required','alpha_dash','max:40',Rule::unique('dental_finding_types','code')->where('facility_id',currentFacility()?->id)],'name'=>['required','string','max:120'],'category'=>['required','string'],'color'=>['nullable','string','max:20'],'icon'=>['nullable','string','max:80'],'applies_to_surface'=>['boolean'],'applies_to_whole_tooth'=>['boolean'],'severity_enabled'=>['boolean'],'is_active'=>['boolean']]);
        DentalFindingType::query()->create([...$data,'facility_id'=>currentFacility()->id,'code'=>strtoupper($data['code']),'created_by'=>auth()->id(),'updated_by'=>auth()->id()]);
        $this->showModal=false; Notifier::success('messages.saved');
    }
    public function render(): View { return view('livewire.dental.settings.findings', ['rows'=>DentalFindingType::query()->forCurrentFacility()->orderBy('sort_order')->paginate(12)])->layout('components.layouts.app',['title'=>'Dental Finding Types','description'=>'Catalog ya findings za odontogram.']); }
}
