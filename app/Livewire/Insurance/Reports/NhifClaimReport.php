<?php
namespace App\Livewire\Insurance\Reports;
use App\Services\InsuranceReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class NhifClaimReport extends Component { public string $status=''; public function mount(): void { Gate::authorize('insurance.reports.view'); } public function render(InsuranceReportService $reports){ return view('livewire.insurance.reports.nhif-claim-report',['claims'=>$reports->claimsQuery(['status'=>$this->status ?: null])->latest()->paginate(25)])->layout('components.layouts.app',['title'=>'NHIF Claim Report','description'=>'Facility claim report/export foundation, not an official electronic NHIF template.']); } }
