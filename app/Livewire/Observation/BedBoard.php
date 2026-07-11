<?php

namespace App\Livewire\Observation;

use App\Models\Bed;
use App\Models\ObservationRoom;
use App\Services\BedManagementService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class BedBoard extends Component
{
    public string $status = ''; public ?int $roomId = null;
    public function mount(): void { Gate::authorize('observation.view-bed-board'); }
    public function markCleaning(int $bedId, BedManagementService $service): void { Gate::authorize('observation.manage-bed-cleaning'); $service->markCleaning(Bed::query()->forCurrentFacility()->findOrFail($bedId), auth()->user()); Notifier::success('observation.bed_cleaning_started'); }
    public function markAvailable(int $bedId, BedManagementService $service): void { Gate::authorize('observation.manage-bed-cleaning'); $service->markAvailable(Bed::query()->forCurrentFacility()->findOrFail($bedId), auth()->user()); Notifier::success('observation.bed_available'); }
    public function render(): View
    {
        $rooms = ObservationRoom::query()->forCurrentFacility()->with(['beds.currentAdmission.patient','beds.activeAssignment'])->when($this->roomId, fn($q)=>$q->whereKey($this->roomId))->where('is_active', true)->orderBy('name')->get();
        return view('livewire.observation.bed-board', ['rooms'=>$rooms, 'allRooms'=>ObservationRoom::query()->forCurrentFacility()->orderBy('name')->get()])->layout('components.layouts.app', ['title'=>'Bed Board','description'=>'Ramani ya vitanda na hali yake.']);
    }
}
