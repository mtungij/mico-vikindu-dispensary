<?php
namespace App\Livewire\Insurance\Claims;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public InsuranceClaim $insuranceClaim;
    public function mount(InsuranceClaim $insuranceClaim): void { Gate::authorize('view', $insuranceClaim); $this->insuranceClaim = $insuranceClaim->load(['patient','provider','scheme','membership','items','attachments','notes']); }
    public function render() { return view('livewire.insurance.claims.show')->layout('components.layouts.app', ['title' => $this->insuranceClaim->claim_number, 'description' => 'Claim details, validation and payment trail.']); }
}
