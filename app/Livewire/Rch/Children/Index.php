<?php

namespace App\Livewire\Rch\Children;

use App\Models\RchChild;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = '';
    public function mount(): void { Gate::authorize('rch.children.view'); }
    public function render(): View { $children = RchChild::query()->forCurrentFacility()->with(['patient','mother'])->when($this->search, fn($q)=>$q->whereHas('patient', fn($p)=>$p->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('patient_number','like',"%{$this->search}%")))->latest()->paginate(12); return view('livewire.rch.children.index', compact('children'))->layout('components.layouts.app', ['title'=>'RCH Children']); }
}
