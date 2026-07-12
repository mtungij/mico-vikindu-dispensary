<?php
namespace App\Livewire\Reports;
use App\Services\InsuranceReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Insurance extends Component { public string $type='claims'; public function mount(string $type='claims'): void { Gate::authorize('insurance.reports.view'); $this->type=$type; } public function render(InsuranceReportService $reports){ return view('livewire.reports.insurance',['claims'=>$reports->claimsQuery()->latest()->limit(100)->get(),'type'=>$this->type])->layout('components.layouts.app',['title'=>'Insurance Reports','description'=>'Claims, outstanding, payments and reconciliation reports.']); } }
