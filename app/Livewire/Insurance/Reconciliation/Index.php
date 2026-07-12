<?php
namespace App\Livewire\Insurance\Reconciliation;
use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Index extends Component { public function mount(): void { Gate::authorize('insurance.reconciliation.manage'); } public function render(){ return view('livewire.insurance.reconciliation.index',['claims'=>InsuranceClaim::query()->forCurrentFacility()->where('outstanding_amount','>',0)->with('provider')->latest()->limit(50)->get()])->layout('components.layouts.app',['title'=>'Insurance Reconciliation','description'=>'Outstanding and short-paid claims.']); } }
