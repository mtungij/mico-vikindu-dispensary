<?php

namespace App\Livewire\Rch\FamilyPlanning;

use App\Models\FamilyPlanningClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = '';
    public function mount(): void { Gate::authorize('rch.family-planning.view'); }
    public function render(): View { $clients = FamilyPlanningClient::query()->forCurrentFacility()->with(['patient','currentMethod'])->when($this->search, fn($q)=>$q->whereHas('patient', fn($p)=>$p->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")))->latest()->paginate(12); return view('livewire.rch.family-planning.index', compact('clients'))->layout('components.layouts.app', ['title'=>'Family Planning']); }
}
