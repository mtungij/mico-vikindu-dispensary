<?php

namespace App\Livewire\Rch\Immunization;

use App\Models\RchChild;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Card extends Component
{
    public RchChild $rchChild;
    public function mount(RchChild $rchChild): void { Gate::authorize('rch.immunization.print-card'); abort_unless($rchChild->facility_id === currentFacility()?->id, 404); $this->rchChild = $rchChild->load(['patient','immunizations.vaccine']); }
    public function render(): View { return view('livewire.rch.immunization.card')->layout('components.layouts.app', ['title'=>'Immunization Card']); }
}
