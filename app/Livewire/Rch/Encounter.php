<?php

namespace App\Livewire\Rch;

use App\Enums\LaboratoryResultStatus;
use App\Enums\PayerType;
use App\Enums\PrescriptionStatus;
use App\Enums\RchService;
use App\Enums\ServiceType;
use App\Livewire\Forms\PrescriptionItemForm;
use App\Livewire\Forms\Rch\RchEncounterForm;
use App\Models\ClinicalProcedureOrder;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\PrescriptionItem;
use App\Models\RchEncounter;
use App\Models\Service;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\LaboratoryOrderItemDecisionService;
use App\Services\LaboratoryResultReleaseService;
use App\Services\MedicationAdministrationService;
use App\Services\PrescriptionService;
use App\Services\ProcedureOrderService;
use App\Services\RchWorkflowService;
use App\Services\ReceptionChargeService;
use App\Services\ServicePricingService;
use App\Support\MedicationDirections;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Encounter extends Component
{
    public Visit $visit;

    public RchEncounter $encounter;

    public RchEncounterForm $form;

    public PrescriptionItemForm $medicine;

    public array $testIds = [];

    public ?string $labNotes = null;

    public array $testCancellationReasons = [];

    public array $procedureIds = [];

    public ?string $procedureInstructions = null;

    public array $procedureFindings = [];

    public array $procedureCancellationReasons = [];

    public array $prescriptionCancellationReasons = [];

    public ?int $followUpServiceId = null;

    public ?string $followUpDate = null;

    public ?string $followUpTime = null;

    public ?string $followUpReason = null;

    public ?int $injectionItem = null;

    public ?string $injectionTime = null;

    public ?string $injectionDose = null;

    public ?string $injectionQuantity = null;

    public ?string $injectionNotes = null;

    public string $medicineSearch = '';

    public function mount(Visit $visit, RchWorkflowService $workflow): void
    {
        $this->visit = $visit;
        try {
            $this->encounter = $workflow->startEncounter($visit, auth()->user());
        } catch (ValidationException $exception) {
            abort(409, $exception->getMessage());
        }
        $this->form->fill($this->encounter->only(array_keys($this->form->rules())));
        if (RchService::tryFrom($visit->consultationService?->code ?? '')) {
            $this->followUpServiceId = $visit->consultation_service_id;
        }
    }

    public function hydrate(): void
    {
        Gate::authorize('rch.start-encounter');
        abort_unless($this->visit->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility()
            && $this->encounter->facility_id === $this->visit->facility_id
            && $this->encounter->visit_id === $this->visit->id, 404);
    }

    public function save(RchWorkflowService $workflow): void
    {
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter): void {
            $encounter->update([...$this->form->validate(), 'updated_by' => auth()->id()]);
        });
        Notifier::success('RCH encounter saved.');
    }

    public function orderTests(RchWorkflowService $workflow, ClinicalEncounterService $services): void
    {
        $data = $this->validate([
            'testIds' => ['required', 'array', 'min:1', 'max:40'],
            'testIds.*' => ['integer', 'distinct'],
            'labNotes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($services, $data): void {
            $services->addLabOrder($encounter->clinicalEncounter, [
                'service_ids' => $data['testIds'],
                'clinical_notes' => $data['labNotes'],
            ], auth()->user());
        });
        $this->reset('testIds', 'labNotes');
        Notifier::success('Laboratory tests ordered.');
    }

    public function cancelTest(int $itemId, RchWorkflowService $workflow, LaboratoryOrderItemDecisionService $decisions): void
    {
        $this->validate(["testCancellationReasons.$itemId" => ['required', 'string', 'max:2000']]);
        $workflow->withEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($itemId, $decisions): void {
            $item = LaboratoryOrderItem::query()->whereHas('order', fn ($query) => $query
                ->where('facility_id', $encounter->facility_id)
                ->where('visit_id', $encounter->visit_id)
                ->where('patient_id', $encounter->patient_id)
                ->where('clinical_encounter_id', $encounter->clinical_encounter_id))->findOrFail($itemId);

            $decisions->decide($item, 'cancelled', 'no_longer_required', trim($this->testCancellationReasons[$itemId]), auth()->user());
        });
        unset($this->testCancellationReasons[$itemId]);
    }

    public function reviewResult(int $resultId, RchWorkflowService $workflow, LaboratoryResultReleaseService $results): void
    {
        $workflow->withEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter, Visit $visit) use ($resultId, $results): void {
            $result = $this->releasedResults($encounter, $visit)->findOrFail($resultId);
            $results->markReviewed($result, auth()->user());
        });
        Notifier::success('Result reviewed.');
    }

    public function addMedicine(RchWorkflowService $workflow, ClinicalEncounterService $services): void
    {
        Gate::authorize('prescriptions.create');
        $this->medicine->validate();
        $data = $this->medicine->normalize();
        $this->validate(['medicine.instructions' => ['required', 'string', 'max:2000']]);
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($services, $data): void {
            if ($encounter->clinicalEncounter->prescriptions()->where('status', PrescriptionStatus::Draft)
                ->whereHas('items', fn ($items) => $items->where('medicine_id', $data['medicine_id']))->exists()) {
                throw ValidationException::withMessages(['medicine.medicine_id' => 'This medicine is already on the draft. Remove the existing line to change it.']);
            }
            $services->addPrescription($encounter->clinicalEncounter, ['items' => [$data]], auth()->user());
        });
        $this->medicine->resetForm();
        $this->reset('medicineSearch');
        Notifier::success('Medicine added to draft.');
    }

    public function removeDraftMedicine(int $itemId, RchWorkflowService $workflow, PrescriptionService $prescriptions): void
    {
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($itemId, $prescriptions): void {
            $item = PrescriptionItem::query()->whereHas('prescription', fn ($query) => $query
                ->where('clinical_encounter_id', $encounter->clinical_encounter_id)
                ->where('status', PrescriptionStatus::Draft))->findOrFail($itemId);
            $prescriptions->removeItem($item, auth()->user());
        });
    }

    public function sendPrescription(int $prescriptionId, RchWorkflowService $workflow): void
    {
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($prescriptionId, $workflow): void {
            $workflow->sendPrescription($encounter->clinicalEncounter->prescriptions()->findOrFail($prescriptionId), auth()->user());
        });
        Notifier::success('Prescription sent.');
    }

    public function cancelPrescription(int $prescriptionId, RchWorkflowService $workflow, PrescriptionService $prescriptions): void
    {
        $this->validate(["prescriptionCancellationReasons.$prescriptionId" => ['required', 'string', 'max:2000']]);
        $workflow->withEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($prescriptionId, $prescriptions): void {
            $prescription = $encounter->clinicalEncounter->prescriptions()->lockForUpdate()->findOrFail($prescriptionId);
            Gate::authorize('cancel', $prescription);
            if ($prescription->status === PrescriptionStatus::Cancelled) {
                return;
            }

            $prescriptions->cancelPrescription($prescription, trim($this->prescriptionCancellationReasons[$prescriptionId]), auth()->user());
        });
        unset($this->prescriptionCancellationReasons[$prescriptionId]);
    }

    public function orderProcedures(RchWorkflowService $workflow): void
    {
        $data = $this->validate([
            'procedureIds' => ['required', 'array', 'min:1', 'max:40'],
            'procedureIds.*' => ['integer', 'distinct'],
            'procedureInstructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter, Visit $visit) use ($workflow, $data): void {
            $workflow->orderProcedures($encounter, $visit, $data['procedureIds'], auth()->user(), $data['procedureInstructions']);
        });
        $this->reset('procedureIds', 'procedureInstructions');
    }

    public function completeProcedure(int $orderId, RchWorkflowService $workflow, ProcedureOrderService $procedures): void
    {
        $this->validate(["procedureFindings.$orderId" => ['required', 'string', 'max:10000']]);
        $workflow->withEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($orderId, $procedures): void {
            $order = $this->procedureOrder($encounter, $orderId);
            Gate::authorize($order->service?->code === RchService::Ultrasound->value ? 'rch.complete-ultrasound' : 'rch.complete-procedure');
            $procedures->completeOrder($order, auth()->user(), $this->procedureFindings[$orderId]);
        });
        unset($this->procedureFindings[$orderId]);
    }

    public function cancelProcedure(int $orderId, RchWorkflowService $workflow, ProcedureOrderService $procedures): void
    {
        $this->validate(["procedureCancellationReasons.$orderId" => ['required', 'string', 'max:2000']]);
        $workflow->withEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter) use ($orderId, $procedures): void {
            $procedures->cancelOrder($this->procedureOrder($encounter, $orderId), $this->procedureCancellationReasons[$orderId], auth()->user());
        });
        unset($this->procedureCancellationReasons[$orderId]);
    }

    public function recordInjection(RchWorkflowService $workflow, MedicationAdministrationService $administrations): void
    {
        Gate::authorize('rch.administer-medication');
        $data = $this->validate([
            'injectionItem' => ['required', 'integer'],
            'injectionTime' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'injectionDose' => ['required', 'string', 'max:255'],
            'injectionQuantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001'],
            'injectionNotes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->withEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter, Visit $visit) use ($administrations, $data): void {
            $item = PrescriptionItem::query()->whereHas('prescription', fn ($query) => $query
                ->where('clinical_encounter_id', $encounter->clinical_encounter_id))->findOrFail($data['injectionItem']);
            $administrations->recordRchInjection($visit, $item, [
                'administered_at' => Carbon::parse($data['injectionTime'])->toDateTimeString(),
                'dose' => $data['injectionDose'],
                'quantity' => $data['injectionQuantity'],
                'notes' => $data['injectionNotes'],
            ], auth()->user());
        }, allowCompleted: true);
        $this->reset('injectionItem', 'injectionTime', 'injectionDose', 'injectionQuantity', 'injectionNotes');
        Notifier::success('Injection administration recorded.');
    }

    public function bookFollowUp(RchWorkflowService $workflow): void
    {
        $data = $this->validate([
            'followUpServiceId' => ['required', 'integer'],
            'followUpDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'followUpTime' => ['required', 'date_format:H:i'],
            'followUpReason' => ['required', 'string', 'max:2000'],
        ]);
        $workflow->withOpenEncounter($this->encounter, auth()->user(), function (RchEncounter $encounter, Visit $visit) use ($workflow, $data): void {
            $workflow->scheduleFollowUp($encounter, $visit, [
                'service_id' => $data['followUpServiceId'],
                'scheduled_start' => Carbon::parse($data['followUpDate'].' '.$data['followUpTime'])->toDateTimeString(),
                'appointment_date' => $data['followUpDate'],
                'appointment_time' => $data['followUpTime'],
                'reason' => $data['followUpReason'],
            ], auth()->user());
        });
        $this->reset('followUpDate', 'followUpTime', 'followUpReason');
        Notifier::success('RCH follow-up booked.');
    }

    public function complete(RchWorkflowService $workflow): mixed
    {
        Gate::authorize('complete', $this->encounter);
        $workflow->completeEncounter($this->encounter, auth()->user(), $this->form->validate());
        Notifier::success(__('rch.consultation_completed'));

        return redirect()->route('rch.index');
    }

    private function releasedResults(RchEncounter $encounter, Visit $visit): Builder
    {
        return LaboratoryResult::query()->forCurrentFacility()
            ->where('result_status', LaboratoryResultStatus::Released)
            ->whereHas('order', fn ($query) => $query->where('clinical_encounter_id', $encounter->clinical_encounter_id)
                ->where('facility_id', $visit->facility_id)->where('visit_id', $visit->id)->where('patient_id', $visit->patient_id))
            ->whereHas('orderItem', fn ($query) => $query->whereNotIn('status', ['cancelled', 'not_performed', 'entered_in_error']));
    }

    private function procedureOrder(RchEncounter $encounter, int $id): ClinicalProcedureOrder
    {
        return $encounter->clinicalEncounter->procedureOrders()->forCurrentFacility()
            ->where('visit_id', $encounter->visit_id)->where('patient_id', $encounter->patient_id)->findOrFail($id);
    }

    public function render(): View
    {
        $visit = $this->visit->refresh()->load(['patient', 'consultationService', 'latestCompletedTriageAssessment', 'destinationDepartment', 'invoice']);
        $clinical = $this->encounter->refresh()->clinicalEncounter;
        $laboratory = auth()->user()->canAny(['laboratory-orders.view', 'laboratory-orders.create', 'laboratory-results.view'])
            ? $clinical->laboratoryOrders()->with('items')->get() : collect();
        $prescriptions = auth()->user()->canAny(['prescriptions.view', 'prescriptions.create', 'rch.administer-medication'])
            ? $clinical->prescriptions()->with('items')->get() : collect();
        $canOrderTests = Gate::allows('create', [LaboratoryOrder::class, $clinical]);
        $visitActive = ! in_array($visit->visit_status->value, ['completed', 'cancelled', 'discharged', 'referred'], true);
        $open = $visitActive && ! $clinical->isReadOnly() && $this->encounter->status === 'in_progress';
        $results = auth()->user()->can('laboratory-results.view')
            ? $this->releasedResults($this->encounter, $visit)->with(['values', 'test'])->latest('released_at')->get() : collect();
        $medicines = [];
        if (auth()->user()->can('prescriptions.create')) {
            $medicines = Medicine::query()->forCurrentFacility()->where('is_active', true)
                ->where(fn ($query) => $query->where('name', 'like', '%'.$this->medicineSearch.'%')
                    ->orWhereHas('generic', fn ($generic) => $generic->where('facility_id', $visit->facility_id)
                        ->where('name', 'like', '%'.$this->medicineSearch.'%'))
                    ->orWhere('id', $this->medicine->medicine_id))
                ->orderBy('name')->limit(75)->get();
        }

        $rchCatalogue = app(ReceptionChargeService::class)->rchServices($visit->destinationDepartment)->orderBy('name')->get();

        $procedureOrders = $clinical->procedureOrders()->forCurrentFacility()->with(['service', 'invoiceItem'])->get();
        $payer = $visit->payerProfile;
        $pricing = app(ServicePricingService::class);
        $testOptions = collect();
        if ($open && $canOrderTests) {
            $invoice = $visit->invoice;
            $labPayer = $invoice ? $invoice->patientPayerProfile : $payer;
            $testOptions = LaboratoryTest::query()->forCurrentFacility()->where('is_active', true)
                ->whereHas('service', fn ($query) => $query->where('is_active', true)->where('facility_id', $visit->facility_id))
                ->with('service')->orderBy('name')->get()
                ->mapWithKeys(function (LaboratoryTest $test) use ($pricing, $visit, $invoice, $labPayer): array {
                    $price = $pricing->getCurrentPrice(
                        $test->service,
                        $invoice?->payer_type ?? $visit->payer_type,
                        $invoice?->insurance_provider_id ?? $labPayer?->insurance_provider_id,
                        $invoice?->corporate_account_id ?? $labPayer?->corporate_account_id,
                    );

                    return [$test->service_id => [
                        'name' => $test->name,
                        'price' => $price ? $pricing->formatMoney($price->amount, $price->currency)
                            : ($test->service->requires_payment ? 'Price unavailable for payer' : 'No charge required'),
                    ]];
                });
        }

        $procedureOptions = collect();
        if ($open && auth()->user()->can('procedure-orders.create')) {
            $prepaidProcedures = $visit->invoice?->items()->where('metadata->charge_source', 'reception_rch_service')
                ->whereNull('reference_id')->where('item_type', ServiceType::Procedure->value)->get()->keyBy('service_id');
            $procedureOptions = Service::query()->forCurrentFacility()
                ->where('service_type', ServiceType::Procedure)->where('is_active', true)
                ->whereNotIn('id', $procedureOrders->pluck('service_id')->filter())->orderBy('name')->get()
                ->mapWithKeys(function (Service $service) use ($pricing, $visit, $payer, $prepaidProcedures): array {
                    $price = $prepaidProcedures?->get($service->id)?->unit_price
                        ?? $pricing->getCurrentPrice($service, $visit->payer_type, $payer?->insurance_provider_id, $payer?->corporate_account_id)?->amount;
                    $billable = $service->requires_payment || $service->code === RchService::Ultrasound->value;

                    return [$service->id => [
                        'name' => $service->name,
                        'price' => $price !== null ? $pricing->formatMoney($price) : match (true) {
                            $visit->payer_type === PayerType::Exempted => 'Exempt',
                            $billable => 'Price unavailable for payer',
                            default => 'No charge required',
                        },
                    ]];
                });
        }

        return view('livewire.rch.encounter', [
            'triage' => $visit->latestCompletedTriageAssessment,
            'rchService' => RchService::tryFrom($visit->consultationService?->code ?? ''),
            'open' => $open,
            'laboratory' => $laboratory,
            'canOrderTests' => $canOrderTests,
            'testOptions' => $testOptions,
            'results' => $results,
            'medicines' => $medicines,
            'prescriptions' => $prescriptions,
            'rchCatalogue' => $rchCatalogue,
            'procedureOptions' => $procedureOptions,
            'procedureOrders' => auth()->user()->can('procedure-orders.view') ? $procedureOrders : collect(),
            'canRecordInjection' => ! in_array($visit->visit_status->value, ['cancelled', 'discharged', 'referred'], true),
            'visitActive' => $visitActive,
            'appointments' => $clinical->appointments()->orderBy('scheduled_start')->get(),
            'injectables' => $prescriptions->flatMap->items->filter(fn (PrescriptionItem $item) => (float) $item->dispensed_quantity > 0
                && in_array(MedicationDirections::normalizeRoute($item->route), ['Intramuscular', 'Intravenous', 'Subcutaneous'], true)),
            'administrations' => auth()->user()->canAny(['rch.administer-medication', 'prescriptions.view']) ? MedicationAdministration::query()->forCurrentFacility()->where('visit_id', $visit->id)
                ->whereNull('observation_admission_id')->orderByDesc('administered_at')->get() : collect(),
        ])->layout('components.layouts.app', ['title' => 'RCH Encounter', 'description' => $visit->patient->fullName()]);
    }
}
