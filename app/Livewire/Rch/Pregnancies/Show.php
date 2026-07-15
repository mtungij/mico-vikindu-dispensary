<?php

namespace App\Livewire\Rch\Pregnancies;

use App\Models\Pregnancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public Pregnancy $pregnancy;
    public function mount(Pregnancy $pregnancy): void { Gate::authorize('rch.pregnancies.view'); abort_unless($pregnancy->facility_id === currentFacility()?->id, 404); $this->pregnancy = $pregnancy->load(['patient','datingRecords','ancRegistration','ancVisits','riskFactors.type','birthPreparednessPlan']); }
    public function render(): View { return view('livewire.rch.pregnancies.show')->layout('components.layouts.app', ['title'=>$this->pregnancy->pregnancy_number]); }
}
