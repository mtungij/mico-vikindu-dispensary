<?php
namespace App\Livewire\Insurance\Batches;
use App\Models\InsuranceClaimBatch;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Show extends Component { public InsuranceClaimBatch $claimBatch; public function mount(InsuranceClaimBatch $claimBatch): void { Gate::authorize('view',$claimBatch); $this->claimBatch=$claimBatch->load('claims.patient'); } public function render(){ return view('livewire.insurance.batches.show')->layout('components.layouts.app',['title'=>$this->claimBatch->batch_number,'description'=>'Batch summary and claims.']); } }
