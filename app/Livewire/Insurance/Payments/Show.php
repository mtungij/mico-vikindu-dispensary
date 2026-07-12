<?php
namespace App\Livewire\Insurance\Payments;
use App\Models\InsurancePayment;
use App\Services\InsurancePaymentService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Show extends Component { public InsurancePayment $insurancePayment; public float $balance=0; public function mount(InsurancePayment $insurancePayment, InsurancePaymentService $service): void { Gate::authorize('view',$insurancePayment); $this->insurancePayment=$insurancePayment->load('allocations.claim'); $this->balance=$service->calculateUnallocatedBalance($insurancePayment); } public function render(){ return view('livewire.insurance.payments.show')->layout('components.layouts.app',['title'=>$this->insurancePayment->payment_reference,'description'=>'Payment allocations.']); } }
