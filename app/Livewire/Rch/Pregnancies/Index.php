<?php

namespace App\Livewire\Rch\Pregnancies;

use App\Models\Pregnancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = ''; public string $risk = ''; public string $status = 'active';
    public function mount(): void { Gate::authorize('rch.pregnancies.view'); }
    public function render(): View
    {
        $pregnancies = Pregnancy::query()->forCurrentFacility()->with('patient')
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->risk === 'high', fn($q) => $q->where('high_risk', true))
            ->when($this->search, fn($q) => $q->whereHas('patient', fn($p) => $p->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('patient_number','like',"%{$this->search}%")))
            ->latest()->paginate(12);
        return view('livewire.rch.pregnancies.index', compact('pregnancies'))->layout('components.layouts.app', ['title'=>'Active Pregnancies']);
    }
}
