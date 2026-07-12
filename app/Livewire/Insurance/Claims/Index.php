<?php
namespace App\Livewire\Insurance\Claims;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $status = '';
    public string $search = '';
    public function mount(): void { Gate::authorize('insurance.claims.view'); }
    public function render()
    {
        return view('livewire.insurance.claims.index', [
            'claims' => InsuranceClaim::query()->forCurrentFacility()->with(['patient','provider','membership','batch'])
                ->when($this->status, fn($q) => $q->where('status',$this->status))
                ->when($this->search, fn($q) => $q->where('claim_number','like','%'.$this->search.'%')->orWhereHas('membership', fn($m) => $m->where('membership_number','like','%'.$this->search.'%')))
                ->latest()->paginate(12),
            'statuses' => ['draft','pending_validation','validation_failed','ready','batched','submitted','approved','partially_approved','rejected','correction_required','resubmitted','paid','closed'],
        ])->layout('components.layouts.app', ['title' => 'Insurance Claims', 'description' => 'Claim queue and status tracking.']);
    }
}
