<?php

namespace App\Livewire\InsuranceProviders;

use App\Enums\InsuranceProviderType;
use App\Models\InsuranceProvider;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $name=''; public string $code=''; public string $provider_type='nhif'; public bool $showModal=false; public ?int $editingId=null;
    public function mount(): void { Gate::authorize('insurance-providers.view'); }
    public function create(): void { Gate::authorize('insurance-providers.create'); $this->reset(['name','code','editingId']); $this->provider_type='nhif'; $this->showModal=true; }
    public function save(): void { $data=$this->validate(['name'=>['required','max:150'],'code'=>['required','alpha_dash','max:40',Rule::unique('insurance_providers','code')->where('facility_id',currentFacility()?->id)->ignore($this->editingId)],'provider_type'=>['required',Rule::enum(InsuranceProviderType::class)]]); InsuranceProvider::query()->updateOrCreate(['id'=>$this->editingId], [...$data,'code'=>str($data['code'])->upper(),'facility_id'=>currentFacility()?->id,'created_by'=>auth()->id(),'updated_by'=>auth()->id()]); $this->showModal=false; Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.insurance-providers.index',['providers'=>InsuranceProvider::query()->forCurrentFacility()->latest()->paginate(10),'types'=>InsuranceProviderType::cases()])->layout('components.layouts.app',['title'=>'Insurance Providers','description'=>'Bima na schemes.']); }
}
