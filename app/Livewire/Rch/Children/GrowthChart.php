<?php

namespace App\Livewire\Rch\Children;

use App\Models\RchChild;
use App\Services\ChildGrowthAssessmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class GrowthChart extends Component
{
    public RchChild $rchChild;
    public function mount(RchChild $rchChild): void { Gate::authorize('rch.growth.view'); abort_unless($rchChild->facility_id === currentFacility()?->id, 404); $this->rchChild = $rchChild->load('patient'); }
    public function render(ChildGrowthAssessmentService $service): View { return view('livewire.rch.children.growth-chart', ['measurements'=>$service->getGrowthTrend($this->rchChild)])->layout('components.layouts.app', ['title'=>'Growth Chart']); }
}
