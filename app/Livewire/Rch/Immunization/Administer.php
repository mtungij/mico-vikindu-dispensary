<?php

namespace App\Livewire\Rch\Immunization;

use App\Livewire\Forms\Rch\ImmunizationAdministrationForm;
use App\Models\RchChild;
use App\Models\Vaccine;
use App\Services\ImmunizationAdministrationService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Administer extends Component
{
    public RchChild $child; public ImmunizationAdministrationForm $form;
    public function mount(RchChild $rchChild): void { Gate::authorize('rch.immunization.administer'); abort_unless($rchChild->facility_id === currentFacility()?->id, 404); $this->child = $rchChild; $this->form->administration_date = today()->toDateString(); }
    public function save(ImmunizationAdministrationService $service): mixed { $data = $this->form->normalize(); $service->administer($this->child, Vaccine::query()->findOrFail($data['vaccine_id']), $data, auth()->user()); Notifier::success('Vaccine administration recorded.'); return redirect()->route('rch.children.show', $this->child); }
    public function render(): View { return view('livewire.rch.immunization.administer', ['vaccines'=>Vaccine::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->where('is_active', true)->get()])->layout('components.layouts.app', ['title'=>'Administer Vaccine']); }
}
