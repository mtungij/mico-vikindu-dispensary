<?php

namespace App\Livewire\Rch\Pregnancies;

use App\Livewire\Forms\Rch\PregnancyForm;
use App\Models\Patient;
use App\Services\PregnancyService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Register extends Component
{
    public PregnancyForm $form;
    public function mount(): void { Gate::authorize('rch.pregnancies.create'); $this->form->lmp_date = today()->subWeeks(12)->toDateString(); }
    public function save(PregnancyService $service): mixed
    {
        $data = $this->form->normalize();
        $pregnancy = $service->register(Patient::query()->forCurrentFacility()->findOrFail($data['patient_id']), $data, auth()->user());
        Notifier::success('Pregnancy registered.');
        return redirect()->route('rch.pregnancies.show', $pregnancy);
    }
    public function render(): View { return view('livewire.rch.pregnancies.register', ['patients' => Patient::query()->forCurrentFacility()->orderBy('first_name')->limit(200)->get()])->layout('components.layouts.app', ['title'=>'Register Pregnancy']); }
}
