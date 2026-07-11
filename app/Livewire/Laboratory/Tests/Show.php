<?php

namespace App\Livewire\Laboratory\Tests;

use App\Livewire\Forms\LaboratoryReferenceRangeForm;
use App\Livewire\Forms\LaboratoryTestParameterForm;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestPanel;
use App\Services\LaboratoryReferenceRangeService;
use App\Services\LaboratoryTestService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public LaboratoryTest $laboratoryTest;
    public LaboratoryTestParameterForm $parameterForm;
    public LaboratoryReferenceRangeForm $rangeForm;
    public ?int $childTestId = null;
    public string $tab = 'parameters';

    public function mount(LaboratoryTest $laboratoryTest): void { Gate::authorize('laboratory.manage-tests'); abort_unless($laboratoryTest->facility_id === currentFacility()?->id, 404); $this->laboratoryTest = $laboratoryTest; }
    public function addParameter(LaboratoryTestService $service): void { Gate::authorize('laboratory.manage-parameters'); $this->parameterForm->validate(); $service->addParameter($this->laboratoryTest, $this->parameterForm->normalize(), auth()->user()); $this->parameterForm->resetForm(); Notifier::success('messages.saved'); }
    public function addReferenceRange(LaboratoryReferenceRangeService $service): void { Gate::authorize('laboratory.manage-reference-ranges'); $this->rangeForm->validate(); $data = $this->rangeForm->normalize(); $service->validateOverlaps($data); LaboratoryReferenceRange::query()->create([...$data, 'facility_id' => $this->laboratoryTest->facility_id, 'laboratory_test_id' => $this->laboratoryTest->id, 'created_by' => auth()->id()]); Notifier::success('messages.saved'); }
    public function addPanelChild(LaboratoryTestService $service): void { Gate::authorize('laboratory.manage-panels'); $child = LaboratoryTest::query()->where('facility_id', $this->laboratoryTest->facility_id)->findOrFail($this->childTestId); $service->addPanelChild($this->laboratoryTest, $child); Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.laboratory.tests.show', ['test' => $this->laboratoryTest->load(['parameters','referenceRanges.parameter','panelChildren.child','service','category','specimenType']), 'availableTests' => LaboratoryTest::query()->forCurrentFacility()->where('id', '!=', $this->laboratoryTest->id)->get()])->layout('components.layouts.app', ['title' => $this->laboratoryTest->name, 'description' => 'Parameters, reference ranges na panel setup.']); }
}
