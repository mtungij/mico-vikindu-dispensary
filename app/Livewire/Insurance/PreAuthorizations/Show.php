<?php
namespace App\Livewire\Insurance\PreAuthorizations;
use App\Models\InsurancePreAuthorization;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Show extends Component { public InsurancePreAuthorization $preAuthorization; public function mount(InsurancePreAuthorization $preAuthorization): void { Gate::authorize('insurance.pre-authorizations.view'); abort_unless($preAuthorization->facility_id===currentFacility()?->id,403); $this->preAuthorization=$preAuthorization->load('patient'); } public function render(){ return view('livewire.insurance.pre-authorizations.show')->layout('components.layouts.app',['title'=>'Pre-authorization','description'=>'Authorization details.']); } }
