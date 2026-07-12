<?php
namespace App\Livewire\Insurance\PreAuthorizations;
use App\Models\InsurancePreAuthorization;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
class Index extends Component { use WithPagination; public function mount(): void { Gate::authorize('insurance.pre-authorizations.view'); } public function render(){ return view('livewire.insurance.pre-authorizations.index',['authorizations'=>InsurancePreAuthorization::query()->forCurrentFacility()->with('patient')->latest()->paginate(12)])->layout('components.layouts.app',['title'=>'Pre-authorizations','description'=>'Authorization request and approval tracking.']); } }
