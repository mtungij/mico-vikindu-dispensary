<?php

namespace App\Livewire\Reports\Observation;

use App\Services\ObservationReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $type = 'admissions';
    public function mount(string $type = 'admissions'): void { Gate::authorize('observation.reports.view'); $this->type=$type; }
    public function render(ObservationReportService $reports): View { $rows = match($this->type){'nursing'=>$reports->nursing()->paginate(15),'medication-administration'=>$reports->medication()->paginate(15),'discharges'=>$reports->discharges()->paginate(15),'bed-cleaning'=>$reports->cleaning()->paginate(15),default=>$reports->admissions()->paginate(15)}; return view('livewire.reports.observation.index',['rows'=>$rows,'type'=>$this->type])->layout('components.layouts.app',['title'=>'Observation Reports','description'=>'Ripoti za Bed Rest / Uangalizi.']); }
}
