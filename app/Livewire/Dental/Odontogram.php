<?php

namespace App\Livewire\Dental;

use App\Livewire\Forms\DentalFindingForm;
use App\Models\DentalEncounter;
use App\Models\DentalFindingType;
use App\Services\DentalOdontogramService;
use App\Services\DentalToothNumberingService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Odontogram extends Component
{
    public DentalEncounter $encounter; public DentalFindingForm $findingForm; public string $dentition = 'permanent'; public ?string $selectedTooth = null; public bool $showFindingModal = false;
    public function mount(DentalEncounter $encounter): void { Gate::authorize('view', $encounter); $this->encounter = $encounter; }
    public function selectTooth(string $tooth): void { $this->selectedTooth = $tooth; $this->findingForm->tooth_number = $tooth; $this->showFindingModal = true; }
    public function addFinding(DentalOdontogramService $service): void { Gate::authorize('dental.add-finding'); $service->addFinding($this->encounter, $this->findingForm->normalize(), auth()->user()); $this->findingForm->resetForm(); $this->showFindingModal=false; Notifier::success('Finding imehifadhiwa.'); }
    public function markMissing(DentalOdontogramService $service, string $tooth): void { Gate::authorize('dental.manage-odontogram'); $service->markToothMissing($this->encounter, $tooth, auth()->user()); }
    public function render(DentalToothNumberingService $numbering): View
    {
        $teeth = $this->dentition === 'primary' ? $numbering->getPrimaryTeeth() : ($this->dentition === 'mixed' ? $numbering->getMixedDentition() : $numbering->getAdultTeeth());
        return view('livewire.dental.odontogram', ['teeth'=>$teeth,'records'=>$this->encounter->toothRecords()->with('findings.type')->get()->keyBy('tooth_number'),'findingTypes'=>DentalFindingType::query()->forCurrentFacility()->where('is_active', true)->orderBy('sort_order')->get(),'surfaces'=>$this->selectedTooth ? $numbering->surfacesFor($this->selectedTooth) : []]);
    }
}
