<?php

namespace App\Livewire\Rch\Immunization;

use App\Models\RchChild;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;
    public function mount(): void { Gate::authorize('rch.immunization.view'); }
    public function render(): View { $children = RchChild::query()->forCurrentFacility()->with('patient')->latest()->paginate(12); return view('livewire.rch.immunization.queue', compact('children'))->layout('components.layouts.app', ['title'=>'Immunization']); }
}
