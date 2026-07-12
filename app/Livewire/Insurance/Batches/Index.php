<?php
namespace App\Livewire\Insurance\Batches;
use App\Models\InsuranceClaimBatch;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
class Index extends Component { use WithPagination; public function mount(): void { Gate::authorize('insurance.claim-batches.view'); } public function render(){ return view('livewire.insurance.batches.index',['batches'=>InsuranceClaimBatch::query()->forCurrentFacility()->latest()->paginate(12)])->layout('components.layouts.app',['title'=>'Claim Batches','description'=>'Batching and submission foundation.']); } }
