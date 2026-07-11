<?php

namespace App\Livewire\Patients;

use App\Livewire\Forms\PatientDocumentForm;
use App\Models\ActivityLog;
use App\Models\Patient;
use App\Services\PatientDocumentService;
use App\Services\ReceptionChargeService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;
    public Patient $patient; public string $tab = 'summary'; public PatientDocumentForm $documentForm; public ?TemporaryUploadedFile $documentFile = null; public bool $showDocumentModal = false; public bool $showCardReplacementModal = false; public string $cardReplacementReason = 'lost'; public int $cardReplacementQuantity = 1; public ?string $cardReplacementDetails = null;
    public function mount(Patient $patient): void { Gate::authorize('view', $patient); $this->patient = $patient->load(['primaryPayerProfile', 'visits.invoice', 'documents', 'contacts', 'invoices.items']); }
    public function openDocumentModal(): void { Gate::authorize('manageDocuments', $this->patient); $this->documentForm->reset(); $this->documentFile = null; $this->showDocumentModal = true; }
    public function uploadDocument(PatientDocumentService $documents): void { Gate::authorize('manageDocuments', $this->patient); $this->validate(['documentFile' => ['required','file','mimes:pdf,jpg,jpeg,png,webp','max:5120']]); $documents->store($this->patient, $this->documentFile, $this->documentForm->data(), auth()->user()); $this->showDocumentModal = false; $this->patient = $this->patient->refresh()->load(['documents','visits.invoice','primaryPayerProfile','contacts','invoices.items']); Notifier::success('documents.uploaded'); }
    public function openCardReplacementModal(): void { Gate::authorize('replaceCard', $this->patient); $this->cardReplacementReason = 'lost'; $this->cardReplacementQuantity = 1; $this->cardReplacementDetails = null; $this->showCardReplacementModal = true; }
    public function replacePatientCard(ReceptionChargeService $charges): void { Gate::authorize('replaceCard', $this->patient); $data = $this->validate(['cardReplacementReason' => ['required','in:lost,damaged,details_changed,other'], 'cardReplacementQuantity' => ['required','integer','min:1','max:5'], 'cardReplacementDetails' => ['nullable','string','max:500']]); $charges->requestPatientCardReplacement($this->patient, ['reason' => $data['cardReplacementReason'], 'quantity' => $data['cardReplacementQuantity'], 'details' => $data['cardReplacementDetails']], auth()->user()); $this->showCardReplacementModal = false; $this->patient = $this->patient->refresh()->load(['documents','visits.invoice','primaryPayerProfile','contacts','invoices.items']); Notifier::success('messages.saved'); }
    public function render(): View
    {
        return view('livewire.patients.show', ['activities' => ActivityLog::query()->where('subject_type', Patient::class)->where('subject_id', $this->patient->id)->latest()->limit(20)->get(), 'dentalEncounters' => \App\Models\DentalEncounter::query()->where('patient_id', $this->patient->id)->with(['provider','procedures','diagnoses','treatmentPlans'])->latest()->limit(20)->get()])
            ->layout('components.layouts.app', ['title' => $this->patient->fullName(), 'description' => $this->patient->patient_number]);
    }
}
