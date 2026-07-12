<?php
namespace App\Livewire\Insurance\Payments;
use App\Models\InsurancePayment;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
class Index extends Component { use WithPagination; public function mount(): void { Gate::authorize('insurance.payments.view'); } public function render(){ return view('livewire.insurance.payments.index',['payments'=>InsurancePayment::query()->forCurrentFacility()->latest()->paginate(12)])->layout('components.layouts.app',['title'=>'Insurance Payments','description'=>'Insurer payments and allocation.']); } }
