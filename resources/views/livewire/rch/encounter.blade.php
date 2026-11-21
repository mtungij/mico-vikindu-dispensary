<div class="space-y-5">
    <x-card>
        <h2 class="text-lg font-semibold">{{ $visit->patient->fullName() }}</h2>
        <p class="text-sm text-slate-500">{{ $visit->patient->patient_number }} · {{ $visit->patient->ageLabel() }} · {{ $visit->visit_number }}</p>
        <p class="mt-2">{{ $rchService?->label() ?? $visit->consultationService?->name ?? 'RCH' }} · {{ $encounter->encounter_number }}</p>
        <p class="text-sm">Consultation status · {{ str($encounter->status)->headline() }}</p>
        <p class="text-sm">Visit status · {{ str($visit->visit_status->value)->headline() }}</p>
        @if ($rchService?->visitRoute() && auth()->user()->can($rchService->visitPermission()))
            <a class="text-sm text-primary" href="{{ route($rchService->visitRoute(), $visit) }}">Open {{ $rchService->label() }} visit</a>
        @endif
        @if ($visit->invoice && auth()->user()->can('billing.view-invoice'))
            <a class="text-sm text-primary" href="{{ route('billing.invoices.show', $visit->invoice) }}">{{ $visit->invoice->invoice_number }}</a>
        @endif
    </x-card>

    @if ($errors->any())
        <div role="alert" class="rounded border border-red-300 p-3">
            <x-input-error :messages="$errors->all()" />
        </div>
    @endif

    <x-card>
        <h3 class="mb-3 font-semibold">Triage summary</h3>
        @if ($triage)
            <dl class="grid gap-2 text-sm sm:grid-cols-3">
                @foreach ([
                    'Temperature' => $triage->temperature === null ? null : $triage->temperature.' °C',
                    'Blood pressure' => $triage->systolic_bp && $triage->diastolic_bp ? $triage->systolic_bp.'/'.$triage->diastolic_bp.' mmHg' : null,
                    'Pulse' => $triage->pulse_rate, 'Respiratory rate' => $triage->respiratory_rate,
                    'Oxygen saturation' => $triage->oxygen_saturation === null ? null : $triage->oxygen_saturation.'%',
                    'Weight' => $triage->weight_kg === null ? null : $triage->weight_kg.' kg',
                    'Height' => $triage->height_cm === null ? null : $triage->height_cm.' cm',
                    'MUAC' => $triage->muac_cm === null ? null : $triage->muac_cm.' cm',
                    'Triage level' => str($triage->triage_level?->value)->headline(),
                    'Presenting concern' => $triage->chief_complaint_summary,
                    'Danger signs' => implode(', ', $triage->danger_signs ?? []),
                    'Pain score' => $triage->pain_score,
                    'Blood glucose' => $triage->blood_glucose,
                    'Gestational age' => $triage->gestational_age_weeks === null ? null : $triage->gestational_age_weeks.' weeks',
                    'Observations' => $triage->notes,
                ] as $label => $value)
                    <div><dt class="text-slate-500">{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
                @endforeach
            </dl>
        @else
            <p>No completed triage assessment.</p>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-3 font-semibold">Clinical notes</h3>
        <form wire:submit="save" class="space-y-3">
            <fieldset @disabled(!$open) class="grid gap-3">
                <label>Chief complaint <x-textarea class="w-full" wire:model="form.chief_complaint" /></label>
                <label>Findings and clinical summary <x-textarea class="w-full" wire:model="form.clinical_summary" rows="4" /></label>
                <label>Treatment plan <x-textarea class="w-full" wire:model="form.treatment_plan" rows="3" /></label>
            </fieldset>
            @if ($open)
                <x-primary-button wire:loading.attr="disabled">Save notes</x-primary-button>
                @can('rch.complete-encounter')
                    <x-secondary-button type="button" wire:click="complete" wire:loading.attr="disabled">{{ __('rch.complete_consultation') }}</x-secondary-button>
                @endcan
            @endif
        </form>
    </x-card>

    @if ($canOrderTests || $laboratory->isNotEmpty() || $results->isNotEmpty())
        <x-card>
            <h3 class="mb-3 font-semibold">Laboratory</h3>
            @if ($open && $canOrderTests)
                <form wire:submit="orderTests" class="space-y-3">
                    <fieldset class="grid max-h-64 gap-2 overflow-auto sm:grid-cols-2">
                        <legend class="mb-2 text-sm">Select tests from the facility catalogue</legend>
                        @forelse ($tests as $test)
                            <label class="flex items-center gap-2" wire:key="test-{{ $test->id }}"><x-checkbox wire:model="testIds" value="{{ $test->service_id }}" /> {{ $test->name }}</label>
                        @empty
                            <p>No active tests configured.</p>
                        @endforelse
                    </fieldset>
                    <label>Laboratory instructions <x-textarea class="w-full" wire:model="labNotes" /></label>
                    <x-primary-button wire:loading.attr="disabled">Order selected tests</x-primary-button>
                </form>
            @endif
            @foreach ($laboratory as $order)
                <div class="mt-4 border-t pt-3" wire:key="lab-{{ $order->id }}">
                    @if(auth()->user()->can('laboratory.view-order') && auth()->user()->can('view', $order))
                        <a class="font-medium text-primary" href="{{ route('laboratory.orders.show', $order) }}">{{ $order->order_number }}</a>
                    @else
                        <p class="font-medium">{{ $order->order_number }}</p>
                    @endif
                    <ul class="mt-1 text-sm">
                        @foreach ($order->items as $item)
                            <li class="mt-2">
                                {{ $item->test_name_snapshot }} · {{ str($item->status)->headline() }} @if($item->terminal_reason) · {{ $item->terminal_reason }} @endif
                                @if ($visitActive && auth()->user()->can('decideItem', $order)
                                    && ! in_array($item->status, ['completed', 'cancelled', 'not_performed', 'entered_in_error'], true)
                                    && ! in_array($item->result_status, ['pending_verification', 'verified', 'released'], true))
                                    <form wire:submit="cancelTest({{ $item->id }})" class="mt-1 flex flex-wrap items-end gap-2">
                                        <label>Cancellation reason <x-text-input wire:model="testCancellationReasons.{{ $item->id }}" required /></label>
                                        <x-secondary-button type="submit" wire:loading.attr="disabled">Cancel test</x-secondary-button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
            @can('laboratory-results.view')
                <h4 class="mt-4 font-medium">Released results</h4>
                @forelse ($results as $result)
                    <div class="mt-2 rounded border p-3 text-sm" wire:key="result-{{ $result->id }}">
                        <p class="font-semibold">{{ $result->test?->name }} · {{ $result->released_at?->format('d M Y H:i') }}</p>
                        @foreach ($result->values as $value)
                            <p>{{ $value->parameter_name_snapshot }} · {{ $value->displayValue() }} {{ $value->unit_snapshot }}</p>
                        @endforeach
                        @if ($result->overall_result)<p>{{ $result->overall_result }}</p>@endif
                        @if ($result->interpretation)<p>{{ $result->interpretation }}</p>@endif
                        @if ($result->comments)<p>{{ $result->comments }}</p>@endif
                        @if ($result->reviewed_at)
                            <p class="mt-2 text-slate-500">Reviewed {{ $result->reviewed_at->format('d M Y H:i') }}</p>
                        @elseif ($visitActive)
                            <x-secondary-button class="mt-2" type="button" wire:click="reviewResult({{ $result->id }})" wire:loading.attr="disabled">Mark reviewed</x-secondary-button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No released results for this encounter.</p>
                @endforelse
            @endcan
        </x-card>
    @endif

    @if ($procedureOrders->isNotEmpty() || ($open && auth()->user()->can('procedure-orders.create')))
        <x-card>
            <h3 class="mb-3 font-semibold">Procedures</h3>
            @if ($open && auth()->user()->can('procedure-orders.create'))
                <form wire:submit="orderProcedures" class="space-y-3">
                    <fieldset class="grid max-h-64 gap-2 overflow-auto sm:grid-cols-2">
                        <legend>Select procedures</legend>
                        @forelse ($procedureOptions as $id => $option)
                            <label class="flex items-center gap-2" wire:key="procedure-option-{{ $id }}">
                                <x-checkbox wire:model="procedureIds" value="{{ $id }}" />
                                <span>{{ $option['name'] }} · {{ $option['price'] !== null ? 'TZS '.number_format($option['price'], 2) : ($option['exempt'] ? 'Exempt' : ($option['billable'] ? 'Price unavailable for payer' : 'No charge required')) }}</span>
                            </label>
                        @empty
                            <p>No additional procedures available.</p>
                        @endforelse
                    </fieldset>
                    @if ($procedureOptions->isNotEmpty())
                        <label>Instructions <x-textarea class="w-full" wire:model="procedureInstructions" /></label>
                        <x-primary-button wire:loading.attr="disabled">Order selected procedures</x-primary-button>
                    @endif
                </form>
            @endif
            @foreach ($procedureOrders as $order)
                <div class="mt-4 space-y-2 border-t pt-3" wire:key="procedure-{{ $order->id }}">
                    <p class="font-medium">{{ $order->procedure_name_snapshot }} · {{ str($order->status->value)->headline() }}</p>
                    @if ($order->invoiceItem)
                        <p class="text-sm">TZS {{ number_format($order->invoiceItem->total_amount, 2) }} · Patient balance TZS {{ number_format(max(0, $order->invoiceItem->patient_amount - $order->invoiceItem->paid_amount), 2) }}</p>
                    @endif
                    @if ($order->instructions)<p>{{ $order->instructions }}</p>@endif
                    @if ($order->notes)<p class="whitespace-pre-wrap">{{ $order->notes }}</p>@endif
                    @if ($visitActive && ! in_array($order->status->value, ['completed', 'cancelled'], true))
                        @if ($order->status->value !== 'awaiting_payment' && auth()->user()->can($order->service?->code === \App\Enums\RchService::Ultrasound->value ? 'rch.complete-ultrasound' : 'rch.complete-procedure'))
                            <form wire:submit="completeProcedure({{ $order->id }})" class="space-y-2">
                                <label>Procedure findings <x-textarea class="w-full" wire:model="procedureFindings.{{ $order->id }}" required /></label>
                                <x-primary-button wire:loading.attr="disabled">Record performed procedure</x-primary-button>
                            </form>
                        @endif
                        @can('cancel', $order)
                            <form wire:submit="cancelProcedure({{ $order->id }})" class="space-y-2">
                                <label>Cancellation reason <x-textarea class="w-full" wire:model="procedureCancellationReasons.{{ $order->id }}" required /></label>
                                <x-secondary-button type="submit" wire:loading.attr="disabled">Cancel procedure</x-secondary-button>
                            </form>
                        @endcan
                    @endif
                </div>
            @endforeach
        </x-card>
    @endif

    @if ($prescriptions->isNotEmpty() || ($open && auth()->user()->can('prescriptions.create')))
        <x-card>
            <h3 class="mb-3 font-semibold">Prescriptions</h3>
            @if ($open)
                @can('prescriptions.create')
                    <form wire:submit="addMedicine" class="grid gap-3 sm:grid-cols-2">
                        <label>Search the medicine catalogue <x-text-input class="w-full" wire:model.live.debounce.300ms="medicineSearch" /></label>
                        <label>Medicine
                            <x-select-input class="w-full" wire:model="medicine.medicine_id" required>
                                <option value="">Select medicine</option>
                                @foreach ($medicines as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }} {{ $option->strength }}</option>
                                @endforeach
                            </x-select-input>
                        </label>
                        <label>Dose and unit <x-text-input class="w-full" wire:model="medicine.dose" required /></label>
                        <label>Route
                            <x-select-input class="w-full" wire:model="medicine.route" required>
                                <option value="">Select route</option>
                                @foreach (\App\Support\MedicationDirections::ROUTES as $route)
                                    <option value="{{ $route }}">{{ $route }}</option>
                                @endforeach
                            </x-select-input>
                        </label>
                        <label>Frequency
                            <x-select-input class="w-full" wire:model="medicine.frequency" required>
                                <option value="">Select frequency</option>
                                @foreach (\App\Support\MedicationDirections::FREQUENCIES as $frequency)
                                    <option value="{{ $frequency }}">{{ $frequency }}</option>
                                @endforeach
                            </x-select-input>
                        </label>
                        <label>Quantity in dispensing units <x-text-input class="w-full" type="number" min="1" step="any" wire:model="medicine.quantity" required /></label>
                        <label>Duration <x-text-input class="w-full" type="number" min="1" wire:model="medicine.duration_value" /></label>
                        <label>Duration unit
                            <x-select-input class="w-full" wire:model="medicine.duration_unit">
                                @foreach (\App\Support\MedicationDirections::DURATION_UNITS as $unit)
                                    <option value="{{ $unit }}">{{ str($unit)->headline() }}</option>
                                @endforeach
                            </x-select-input>
                        </label>
                        <label class="sm:col-span-2">Instructions <x-textarea class="w-full" wire:model="medicine.instructions" required /></label>
                        <div><x-primary-button wire:loading.attr="disabled">Add to prescription draft</x-primary-button></div>
                    </form>
                @endcan
            @endif
            @foreach ($prescriptions as $prescription)
                <div class="mt-4 border-t pt-3" wire:key="rx-{{ $prescription->id }}">
                    <p class="font-medium">{{ $prescription->prescription_number }} · {{ str($prescription->status->value)->headline() }}</p>
                    @foreach ($prescription->items as $item)
                        <div class="mt-2 text-sm" wire:key="rx-item-{{ $item->id }}">
                            <p>{{ $item->medication_name }} · Quantity {{ $item->quantity }} · Dispensed {{ $item->dispensed_quantity }}</p>
                            <p>{{ $item->dose }} · {{ $item->route }} · {{ $item->frequency }} · {{ $item->duration_value }} {{ str($item->duration_unit)->replace('_', ' ') }}</p>
                            <p>{{ $item->instructions }}</p>
                            @if ($open && $prescription->status->value === 'draft' && auth()->user()->can('prescriptions.update'))
                                <button type="button" class="text-sm text-red-600" wire:click="removeDraftMedicine({{ $item->id }})" wire:loading.attr="disabled">Remove from draft</button>
                            @endif
                        </div>
                    @endforeach
                    @if ($open && $prescription->status->value === 'draft' && $prescription->items->isNotEmpty() && auth()->user()->can('prescriptions.update'))
                        <x-secondary-button class="mt-2" type="button" wire:click="sendPrescription({{ $prescription->id }})" wire:loading.attr="disabled">Send prescription for billing and dispensing</x-secondary-button>
                    @endif
                    @if ($visitActive && auth()->user()->can('cancel', $prescription)
                        && in_array($prescription->status->value, ['draft', 'awaiting_payment', 'prescribed'], true)
                        && $prescription->items->every(fn ($item) => (float) $item->dispensed_quantity === 0.0))
                        <form wire:submit="cancelPrescription({{ $prescription->id }})" class="mt-2 flex flex-wrap items-end gap-2">
                            <label>Cancellation reason <x-text-input wire:model="prescriptionCancellationReasons.{{ $prescription->id }}" required /></label>
                            <x-secondary-button type="submit" wire:loading.attr="disabled">Cancel prescription</x-secondary-button>
                        </form>
                    @endif
                </div>
            @endforeach
        </x-card>
    @endif

    @if ($administrations->isNotEmpty() || ($canRecordInjection && $injectables->isNotEmpty() && auth()->user()->can('rch.administer-medication')))
        <x-card>
            <h3 class="mb-3 font-semibold">Injection administration</h3>
            @if ($canRecordInjection && $injectables->isNotEmpty())
                @can('rch.administer-medication')
                    <form wire:submit="recordInjection" class="grid gap-3 sm:grid-cols-2">
                        <label>Dispensed medicine
                            <x-select-input class="w-full" wire:model="injectionItem" required>
                                <option value="">Select medicine</option>
                                @foreach ($injectables as $item)
                                    <option value="{{ $item->id }}">{{ $item->medication_name }} · {{ $item->dose }}</option>
                                @endforeach
                            </x-select-input>
                        </label>
                        <label>Time administered <x-text-input class="w-full" type="datetime-local" wire:model="injectionTime" required /></label>
                        <label>Dose given <x-text-input class="w-full" wire:model="injectionDose" required /></label>
                        <label>Quantity used in dispensing units <x-text-input class="w-full" type="number" min="0.001" step="0.001" wire:model="injectionQuantity" required /></label>
                        <label class="sm:col-span-2">Administration notes <x-textarea class="w-full" wire:model="injectionNotes" /></label>
                        <div><x-primary-button wire:loading.attr="disabled">Record injection given</x-primary-button></div>
                    </form>
                @endcan
            @endif
            @foreach ($administrations as $administration)
                <p class="mt-2 text-sm">{{ $administration->administered_at?->format('d M Y H:i') }} · {{ $administration->medicine_name_snapshot }} · {{ $administration->dose }} · {{ $administration->route }} @if($administration->notes) · {{ $administration->notes }} @endif</p>
            @endforeach
        </x-card>
    @endif

    <x-card>
        <h3 class="mb-3 font-semibold">Follow-up</h3>
        @foreach ($appointments as $appointment)
            <p class="mb-2 text-sm">{{ $appointment->appointment_number }} · {{ $appointment->scheduled_start->format('d M Y H:i') }} · {{ $appointment->reason }} · {{ str($appointment->status->value)->headline() }}</p>
        @endforeach
        @if ($open)
            @can('appointments.create')
                <form wire:submit="bookFollowUp" class="grid gap-3 sm:grid-cols-2">
                    <label>Date <x-text-input class="w-full" type="date" wire:model="followUpDate" required /></label>
                    <label>Time <x-text-input class="w-full" type="time" wire:model="followUpTime" required /></label>
                    <label class="sm:col-span-2">RCH service
                        <x-select-input class="w-full" wire:model="followUpServiceId" required>
                            <option value="">Select service</option>
                            @foreach ($rchCatalogue as $option)
                                <option value="{{ $option->id }}">{{ \App\Enums\RchService::from($option->code)->label() }}</option>
                            @endforeach
                        </x-select-input>
                    </label>
                    <label class="sm:col-span-2">Reason <x-textarea class="w-full" wire:model="followUpReason" required /></label>
                    <div><x-primary-button wire:loading.attr="disabled">Book follow-up</x-primary-button></div>
                </form>
            @endcan
        @endif
    </x-card>
</div>
