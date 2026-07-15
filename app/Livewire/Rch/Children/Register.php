<?php

namespace App\Livewire\Rch\Children;

use App\Livewire\Forms\Rch\RchChildForm;
use App\Models\Patient;
use App\Services\RchChildService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Register extends Component
{
    public RchChildForm $form;
    public function mount(): void { Gate::authorize('rch.children.register'); $this->form->birth_date = today()->toDateString(); }
    public function save(RchChildService $service): mixed { $data = $this->form->normalize(); $child = $service->register(Patient::query()->forCurrentFacility()->findOrFail($data['child_patient_id']), $data, auth()->user()); Notifier::success('Child registered in RCH.'); return redirect()->route('rch.children.show', $child); }
    public function render(): View { return view('livewire.rch.children.register', ['patients'=>Patient::query()->forCurrentFacility()->orderBy('first_name')->limit(250)->get()])->layout('components.layouts.app', ['title'=>'Register Child']); }
}
