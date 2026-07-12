<?php
namespace App\Livewire\Billing\Reports;
use App\Services\BillingReportService; use Illuminate\Support\Facades\Gate; use Livewire\Component;
class Index extends Component { public string $type='collections'; public function mount(string $type='collections'): void { Gate::authorize('billing.reports.view'); $this->type=$type; } public function render(BillingReportService $reports){ return view('livewire.billing.reports.index',['type'=>$this->type,'invoices'=>$reports->invoices()->latest()->limit(100)->get(),'payments'=>$reports->payments()->latest()->limit(100)->get()])->layout('components.layouts.app',['title'=>'Billing Reports','description'=>'Collections, pending bills, cashier and revenue reports.']); } }
