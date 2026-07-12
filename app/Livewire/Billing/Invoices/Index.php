<?php
namespace App\Livewire\Billing\Invoices;
use App\Models\Invoice; use Illuminate\Support\Facades\Gate; use Livewire\Component; use Livewire\WithPagination;
class Index extends Component { use WithPagination; public string $search=''; public function mount(): void { Gate::authorize('billing.view-invoice'); } public function render(){ return view('livewire.billing.invoices.index',['invoices'=>Invoice::query()->forCurrentFacility()->with('patient')->when($this->search,fn($q)=>$q->where('invoice_number','like','%'.$this->search.'%'))->latest()->paginate(12)])->layout('components.layouts.app',['title'=>'Invoices','description'=>'Invoice management and payment status.']); } }
