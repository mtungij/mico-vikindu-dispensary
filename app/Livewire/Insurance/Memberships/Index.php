<?php
namespace App\Livewire\Insurance\Memberships;
use App\Models\PatientInsuranceMembership;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
class Index extends Component { use WithPagination; public string $search=''; public function mount(): void { Gate::authorize('insurance.view-memberships'); } public function render(){ return view('livewire.insurance.memberships.index',['memberships'=>PatientInsuranceMembership::query()->forCurrentFacility()->with(['patient','provider','scheme'])->when($this->search, fn($q)=>$q->where('membership_number','like','%'.$this->search.'%'))->latest()->paginate(12)])->layout('components.layouts.app',['title'=>'Insurance Memberships','description'=>'Patient insurance membership and verification.']); } }
