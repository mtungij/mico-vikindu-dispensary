<?php

namespace App\Livewire\Pharmacy\Returns;

use App\Models\PharmacyReturn;
use App\Models\SupplierReturn;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component { public string $type = 'patient'; public function mount(string $type = 'patient'): void { Gate::authorize($type === 'supplier' ? 'pharmacy.return-to-supplier' : 'pharmacy.manage-patient-returns'); $this->type = $type; } public function render(): View { $rows = $this->type === 'supplier' ? SupplierReturn::query()->forCurrentFacility()->latest()->paginate(12) : PharmacyReturn::query()->forCurrentFacility()->latest()->paginate(12); return view('livewire.pharmacy.returns.index', ['rows' => $rows, 'type' => $this->type])->layout('components.layouts.app', ['title' => $this->type === 'supplier' ? 'Supplier Returns' : 'Patient Returns', 'description' => 'Returns na reversal foundation.']); } }
