<div class="space-y-6">
    @php
        $triage = $visit->latestTriageAssessment;
        $invoice = $visit->invoice;
        $payerProfile = $visit->patient->primaryPayerProfile;
        $previousDiagnoses = $visit->patient->diagnoses
            ->reject(fn ($diagnosis) => (int) $diagnosis->clinical_encounter_id === (int) $encounter->id)
            ->sortByDesc('diagnosed_at')
            ->take(5);
        $statusTone = [
            'awaiting_payment' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
            'ordered' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
            'sample_pending' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200',
            'processing' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
            'result_ready' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
            'verified' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-200',
            'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            'prescribed' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
            'prepared' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
        ];
        $statusValue = fn ($status) => $status instanceof \BackedEnum ? $status->value : (string) ($status ?? '');
        $statusLabel = fn ($status) => str($statusValue($status))->replace('_', ' ')->title();
        $badge = fn ($status) => $statusTone[$statusValue($status)] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
        $labServiceIdsWithTests = $labTests->pluck('service_id')->filter()->all();
        $catalogueOnlyServices = $labServices->whereNotIn('id', $labServiceIdsWithTests);
    @endphp

    <div class="sticky top-16 z-20 rounded-md border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-card-dark">
        <div class="grid gap-3 md:grid-cols-6">
            <div><p class="text-xs text-slate-500">Patient</p><p class="font-semibold">{{ $visit->patient->fullName() }}</p><p class="text-xs text-slate-500">{{ $visit->patient->patient_number }}</p></div>
            <div><p class="text-xs text-slate-500">Age/Gender</p><p class="font-semibold">{{ $visit->patient->ageLabel() }} / {{ $visit->patient->gender?->label() ?? $visit->patient->gender?->value }}</p></div>
            <div><p class="text-xs text-slate-500">Visit</p><p class="font-semibold">{{ $visit->visit_number }}</p><p class="text-xs text-slate-500">{{ $visit->payer_type?->label() ?? $visit->payer_type?->value }}</p></div>
            <div><p class="text-xs text-slate-500">Department</p><p class="font-semibold">{{ $visit->currentDepartment?->name ?? '-' }}</p><p class="text-xs text-slate-500">{{ $visit->visit_status?->value ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-500">Queue</p><p class="font-semibold">{{ $visit->currentQueue?->queue_number ?? '-' }}</p><p class="text-xs text-slate-500">{{ $visit->currentQueue?->queue_status?->label() ?? $visit->currentQueue?->queue_status?->value ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-500">Encounter</p><p class="font-semibold">{{ $encounter->encounter_number }}</p><p class="text-xs text-slate-500">{{ $encounter->status?->value ?? $encounter->status }} · {{ $saveState }}</p></div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[18rem_1fr_20rem]">
        <aside class="space-y-4">
            <x-card>
                <h3 class="mb-3 font-semibold">Patient Snapshot</h3>
                <div class="space-y-2 text-sm">
                    <p><span class="text-slate-500">Allergies:</span> {{ $visit->patient->known_allergies ?: 'Hakuna' }}</p>
                    <p><span class="text-slate-500">Chronic:</span> {{ $visit->patient->chronic_conditions ?: 'Hakuna' }}</p>
                    <p><span class="text-slate-500">Payment:</span> {{ $invoice?->payment_status ?? '-' }}</p>
                    <p><span class="text-slate-500">Provider:</span> {{ $encounter->provider?->name ?? $visit->currentAssignedUser?->name ?? '-' }}</p>
                </div>
            </x-card>
            <x-card><h3 class="mb-3 font-semibold">Clinical Timeline</h3><livewire:clinical.patient-timeline :patient="$visit->patient" /></x-card>
        </aside>

        <main class="space-y-6">
            <div class="flex flex-wrap gap-2">
                @foreach(['summary' => 'Summary', 'history' => 'History', 'exam' => 'Examination', 'diagnoses' => 'Diagnosis', 'orders' => 'Orders', 'results' => 'Results', 'plan' => 'Plan', 'follow' => 'Follow-up'] as $key => $label)
                    <button type="button" wire:click="$set('activeTab','{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $activeTab === $key ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }}">{{ $label }}</button>
                @endforeach
            </div>

            @if($activeTab === 'summary')
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-card>
                        <h3 class="mb-3 font-semibold">Patient Demographics</h3>
                        <dl class="grid gap-3 text-sm md:grid-cols-2">
                            <div><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $visit->patient->fullName() }}</dd></div>
                            <div><dt class="text-slate-500">Patient No.</dt><dd class="font-medium">{{ $visit->patient->patient_number }}</dd></div>
                            <div><dt class="text-slate-500">Age / Gender</dt><dd class="font-medium">{{ $visit->patient->ageLabel() }} / {{ $visit->patient->gender?->label() ?? $visit->patient->gender?->value ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $visit->patient->primary_phone ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Address</dt><dd class="font-medium">{{ collect([$visit->patient->region, $visit->patient->district, $visit->patient->ward])->filter()->implode(', ') ?: '-' }}</dd></div>
                            <div><dt class="text-slate-500">Registered</dt><dd class="font-medium">{{ $visit->patient->registered_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                        </dl>
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Visit Information</h3>
                        <dl class="grid gap-3 text-sm md:grid-cols-2">
                            <div><dt class="text-slate-500">Visit No.</dt><dd class="font-medium">{{ $visit->visit_number }}</dd></div>
                            <div><dt class="text-slate-500">Visit Type</dt><dd class="font-medium">{{ $visit->visit_type?->value ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Visit Status</dt><dd class="font-medium">{{ $visit->visit_status?->value ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Queue Status</dt><dd class="font-medium">{{ $visit->currentQueue?->queue_status?->value ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Current Department</dt><dd class="font-medium">{{ $visit->currentDepartment?->name ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Assigned Provider</dt><dd class="font-medium">{{ $encounter->provider?->name ?? $visit->currentAssignedUser?->name ?? '-' }}</dd></div>
                        </dl>
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Latest Triage Vitals</h3>
                        @if($triage)
                            <dl class="grid gap-3 text-sm md:grid-cols-2">
                                <div><dt class="text-slate-500">Triage Level</dt><dd class="font-medium">{{ $triage->triage_level?->value ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">Temperature</dt><dd class="font-medium">{{ $triage->temperature ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">Blood Pressure</dt><dd class="font-medium">{{ $triage->systolic_bp ?? '-' }}/{{ $triage->diastolic_bp ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">Pulse</dt><dd class="font-medium">{{ $triage->pulse_rate ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">Respiratory Rate</dt><dd class="font-medium">{{ $triage->respiratory_rate ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">SpO2</dt><dd class="font-medium">{{ $triage->oxygen_saturation ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">BMI</dt><dd class="font-medium">{{ $triage->bmi ?? '-' }}</dd></div>
                                <div><dt class="text-slate-500">Pain Score</dt><dd class="font-medium">{{ $triage->pain_score ?? '-' }}</dd></div>
                            </dl>
                        @else
                            <p class="text-sm text-slate-500">No triage vitals recorded.</p>
                        @endif
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Clinical Background</h3>
                        <div class="space-y-3 text-sm">
                            <div><p class="text-slate-500">Allergies</p><p class="font-medium">{{ $visit->patient->known_allergies ?: 'Hakuna' }}</p></div>
                            <div><p class="text-slate-500">Chronic Diseases</p><p class="font-medium">{{ $visit->patient->chronic_conditions ?: 'Hakuna' }}</p></div>
                            <div>
                                <p class="text-slate-500">Previous Diagnoses</p>
                                @forelse($previousDiagnoses as $diagnosis)
                                    <p class="font-medium">{{ $diagnosis->diagnosis_name }} <span class="text-xs text-slate-500">{{ $diagnosis->diagnosed_at?->format('d/m/Y') }}</span></p>
                                @empty
                                    <p class="font-medium">Hakuna</p>
                                @endforelse
                            </div>
                        </div>
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Payment / Insurance</h3>
                        <dl class="grid gap-3 text-sm md:grid-cols-2">
                            <div><dt class="text-slate-500">Payer Type</dt><dd class="font-medium">{{ $visit->payer_type?->label() ?? $visit->payer_type?->value ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Coverage</dt><dd class="font-medium">{{ $payerProfile?->coverage_status?->value ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Insurance / Corporate</dt><dd class="font-medium">{{ $invoice?->insuranceProvider?->name ?? $payerProfile?->insuranceProvider?->name ?? $invoice?->corporateAccount?->name ?? $payerProfile?->corporateAccount?->name ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Membership</dt><dd class="font-medium">{{ $payerProfile?->membership_number ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Invoice</dt><dd class="font-medium">{{ $invoice?->invoice_number ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Payment Status</dt><dd class="font-medium">{{ $invoice?->payment_status ?? '-' }}</dd></div>
                        </dl>
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Previous Encounters</h3>
                        <div class="space-y-2 text-sm">
                            @forelse($visit->patient->clinicalEncounters()->whereKeyNot($encounter->id)->latest('started_at')->limit(5)->get() as $previous)
                                <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="font-medium">{{ $previous->encounter_number }}</p>
                                    <p class="text-xs text-slate-500">{{ $previous->started_at?->format('d/m/Y H:i') }} · {{ $previous->status?->value ?? $previous->status }}</p>
                                </div>
                            @empty
                                <p class="text-slate-500">No previous encounters.</p>
                            @endforelse
                        </div>
                    </x-card>
                </div>
            @elseif($activeTab === 'history')
                <x-card>
                    <h3 class="mb-3 font-semibold">Clinical History</h3>
                    <div class="grid gap-4">
                        <x-textarea wire:model.live.debounce.2000ms="form.chief_complaint" wire:change="autosave" rows="2" placeholder="Chief complaint" />
                        <x-textarea wire:model.live.debounce.2000ms="form.history_of_presenting_illness" wire:change="autosave" rows="5" placeholder="History of present illness" />
                        <x-textarea wire:model.live.debounce.2000ms="form.past_medical_history" wire:change="autosave" rows="3" placeholder="Past medical history" />
                        <x-textarea wire:model.live.debounce.2000ms="form.medication_history" wire:change="autosave" rows="3" placeholder="Medication history" />
                        <x-textarea wire:model.live.debounce.2000ms="form.allergy_history" wire:change="autosave" rows="3" placeholder="Allergy history" />
                        <x-textarea wire:model.live.debounce.2000ms="form.family_history" wire:change="autosave" rows="3" placeholder="Family history" />
                        <x-textarea wire:model.live.debounce.2000ms="form.social_history" wire:change="autosave" rows="3" placeholder="Social history" />
                    </div>
                </x-card>
                <x-card><h3 class="mb-3 font-semibold">Structured Complaints</h3><div class="grid gap-3 md:grid-cols-2"><x-text-input wire:model="complaintForm.complaint" placeholder="Complaint" /><x-select-input wire:model="complaintForm.severity"><option value="">Severity</option><option value="mild">Mild</option><option value="moderate">Moderate</option><option value="severe">Severe</option></x-select-input><x-text-input wire:model="complaintForm.duration_value" placeholder="Duration" /><x-select-input wire:model="complaintForm.duration_unit"><option value="">Unit</option><option value="hours">Hours</option><option value="days">Days</option><option value="weeks">Weeks</option><option value="months">Months</option></x-select-input></div><x-primary-button type="button" wire:click="addComplaint" class="mt-3">Add Complaint</x-primary-button></x-card>
            @elseif($activeTab === 'exam')
                <x-card>
                    <h3 class="mb-3 font-semibold">Read-only Triage Vitals</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Vitals are captured in Triage and displayed here for clinical reference.</p>
                    <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                        <div><p class="text-slate-500">Temp</p><p class="font-medium">{{ $triage->temperature ?? '-' }}</p></div>
                        <div><p class="text-slate-500">BP</p><p class="font-medium">{{ $triage->systolic_bp ?? '-' }}/{{ $triage->diastolic_bp ?? '-' }}</p></div>
                        <div><p class="text-slate-500">Pulse</p><p class="font-medium">{{ $triage->pulse_rate ?? '-' }}</p></div>
                        <div><p class="text-slate-500">SpO2</p><p class="font-medium">{{ $triage->oxygen_saturation ?? '-' }}</p></div>
                    </div>
                </x-card>
                <x-card><h3 class="mb-3 font-semibold">General / System Examination</h3><div class="grid gap-3 md:grid-cols-3"><x-select-input wire:model="examForm.examination_system"><option value="general">General</option><option value="cardiovascular">Cardiovascular</option><option value="respiratory">Respiratory</option><option value="gastrointestinal">Gastrointestinal</option><option value="neurological">Neurological</option><option value="musculoskeletal">Musculoskeletal</option><option value="skin">Skin</option><option value="ent">ENT</option><option value="eyes">Eyes</option><option value="oral">Oral</option><option value="obstetric">Obstetric</option><option value="other">Other</option></x-select-input><x-select-input wire:model="examForm.status"><option value="normal">Normal</option><option value="abnormal">Abnormal</option><option value="not_examined">Not examined</option></x-select-input></div><x-textarea wire:model="examForm.findings" rows="5" class="mt-3" placeholder="Findings" /><x-primary-button type="button" wire:click="saveExamination" class="mt-3">Save Examination</x-primary-button></x-card>
                <x-card><h3 class="mb-3 font-semibold">Recorded Examinations</h3><div class="space-y-3">@forelse($encounter->examinations as $exam)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-semibold">{{ str($exam->examination_system)->replace('_',' ')->title() }} · {{ $exam->status }}</p><p class="text-sm">{{ $exam->findings }}</p></div>@empty<p class="text-sm text-slate-500">No examination findings recorded.</p>@endforelse</div></x-card>
            @elseif($activeTab === 'diagnoses')
                <x-card><h3 class="mb-3 font-semibold">Diagnosis Entry</h3><livewire:clinical.icd10-search /><div class="mt-3 grid gap-3 md:grid-cols-2"><x-text-input wire:model="diagnosisForm.icd10_code" placeholder="ICD-10 code" /><x-text-input wire:model="diagnosisForm.diagnosis_name" placeholder="Diagnosis name" /><x-select-input wire:model="diagnosisForm.diagnosis_type"><option value="provisional">Provisional</option><option value="differential">Differential</option><option value="final">Final</option><option value="confirmed">Confirmed</option><option value="rule_out">Rule out</option></x-select-input><x-select-input wire:model="diagnosisForm.certainty"><option value="suspected">Suspected</option><option value="probable">Probable</option><option value="confirmed">Confirmed</option></x-select-input></div><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="diagnosisForm.is_primary"> Primary diagnosis</label><x-primary-button type="button" wire:click="addDiagnosis" class="mt-3">Add Diagnosis</x-primary-button></x-card>
                <x-card><h3 class="mb-3 font-semibold">Encounter Diagnoses</h3><div class="space-y-3">@forelse($encounter->diagnoses as $diagnosis)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><div class="flex items-center justify-between gap-3"><p class="font-semibold">{{ $diagnosis->diagnosis_name }}</p>@if($diagnosis->is_primary)<span class="rounded-full bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">Primary</span>@else<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Secondary</span>@endif</div><p class="text-xs text-slate-500">{{ $diagnosis->diagnosis_type?->value }} · {{ $diagnosis->certainty?->value }} · {{ $diagnosis->icd10_code }}</p></div>@empty<p class="text-sm text-slate-500">No diagnoses recorded.</p>@endforelse</div></x-card>
            @elseif($activeTab === 'orders')
                <div class="space-y-4">
                    <x-card>
                        <div class="flex items-center justify-between gap-3"><div><h3 class="font-semibold">Laboratory Test Catalogue</h3><p class="text-sm text-slate-500">Select tests from the catalogue. These are not ordered until submitted.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Available Tests</span></div>
                        <div class="mt-4 grid gap-2 md:grid-cols-2">
                            @forelse($labTests as $test)
                                @if($test->service)
                                    <label class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                                        <input type="checkbox" wire:model="labForm.service_ids" value="{{ $test->service_id }}" class="mt-1">
                                        <span><span class="font-medium">{{ $test->name }}</span><span class="block text-xs text-slate-500">{{ $test->code }} · {{ $test->category?->name }} · {{ $test->specimenType?->name ?? 'Specimen not set' }}</span></span>
                                    </label>
                                @endif
                            @empty
                                @foreach($catalogueOnlyServices as $service)
                                    <label class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><input type="checkbox" wire:model="labForm.service_ids" value="{{ $service->id }}" class="mt-1"><span><span class="font-medium">{{ $service->name }}</span><span class="block text-xs text-slate-500">{{ $service->code }}</span></span></label>
                                @endforeach
                            @endforelse
                        </div>
                        <x-textarea wire:model="labForm.clinical_notes" rows="2" class="mt-3" placeholder="Clinical notes for laboratory" />
                        <x-primary-button type="button" wire:click="addLabOrder" class="mt-3">Order Selected Tests</x-primary-button>
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Ordered Laboratory Tests</h3>
                        <div class="space-y-2">
                            @forelse($encounter->laboratoryOrders as $order)
                                @foreach($order->items as $item)
                                    <div class="flex items-center justify-between gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><div><p class="font-medium">{{ $item->test_name_snapshot }}</p><p class="text-xs text-slate-500">{{ $order->order_number }} · {{ $order->ordered_at?->format('d/m/Y H:i') }}</p></div><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $badge($order->status) }}">{{ $statusLabel($order->status) }}</span></div>
                                @endforeach
                            @empty
                                <p class="text-sm text-slate-500">No laboratory tests ordered yet.</p>
                            @endforelse
                        </div>
                    </x-card>

                    <x-card>
                        <h3 class="mb-3 font-semibold">Medication Orders</h3>
                        <div class="grid gap-3 md:grid-cols-2">
                            @if($medicines->isNotEmpty())
                                <x-select-input wire:model="prescriptionItemForm.medicine_id"><option value="">Select medicine</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->name }} {{ $medicine->strength ? '· '.$medicine->strength : '' }}</option>@endforeach</x-select-input>
                            @else
                                <x-text-input wire:model="prescriptionItemForm.medication_name" placeholder="Medicine" />
                            @endif
                            <x-text-input wire:model="prescriptionItemForm.dose" placeholder="Dose" />
                            <x-text-input wire:model="prescriptionItemForm.frequency" placeholder="Frequency" />
                            <x-text-input wire:model="prescriptionItemForm.duration_value" placeholder="Duration" />
                            <x-select-input wire:model="prescriptionItemForm.duration_unit"><option value="days">Days</option><option value="weeks">Weeks</option><option value="months">Months</option></x-select-input>
                            <x-text-input wire:model="prescriptionItemForm.instructions" placeholder="Instructions" />
                        </div>
                        <x-primary-button type="button" wire:click="addPrescription" class="mt-3">Add Medication Order</x-primary-button>
                    </x-card>

                    <x-card><h3 class="mb-3 font-semibold">Procedure Orders</h3><x-select-input wire:model="procedureForm.service_id"><option value="">Select procedure service</option>@foreach($procedureServices as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</x-select-input><x-text-input wire:model="procedureForm.procedure_name_snapshot" placeholder="Or enter procedure name" class="mt-3" /><x-textarea wire:model="procedureForm.instructions" rows="2" class="mt-3" placeholder="Instructions" /><x-primary-button type="button" wire:click="addProcedure" class="mt-3">Order Procedure</x-primary-button></x-card>

                    <x-card><h3 class="mb-3 font-semibold">Radiology Orders</h3><div class="rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">Coming Soon</div></x-card>

                    <x-card><h3 class="mb-3 font-semibold">Referral Orders</h3><x-text-input wire:model="referralForm.destination_facility_name" placeholder="Destination facility" /><x-textarea wire:model="referralForm.reason" rows="3" class="mt-3" placeholder="Reason" /><x-select-input wire:model="referralForm.urgency" class="mt-3"><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></x-select-input><x-primary-button type="button" wire:click="createReferral" class="mt-3">Prepare Referral</x-primary-button></x-card>
                </div>
            @elseif($activeTab === 'results')
                <div class="space-y-4">
                    <x-card>
                        <h3 class="mb-3 font-semibold">Laboratory Results</h3>
                        <div class="space-y-2">
                            @forelse($encounter->laboratoryOrders as $order)
                                @foreach($order->items as $item)
                                    @php $result = $item->results->sortByDesc('created_at')->first(); @endphp
                                    <div class="rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                                        <div class="flex items-center justify-between gap-3"><p class="font-medium">{{ $item->test_name_snapshot }}</p><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $badge($result?->result_status ?? $item->result_status ?? $order->status) }}">{{ $statusLabel($result?->result_status ?? $item->result_status ?? $order->status) }}</span></div>
                                        <p class="mt-1 text-xs text-slate-500">Requested {{ $order->ordered_at?->format('d/m/Y H:i') }} · {{ $item->result_entered_at ? 'Completed '.$item->result_entered_at->format('d/m/Y H:i') : 'Result pending' }}</p>
                                        @if($result)<p class="mt-2">{{ $result->overall_result ?? $result->interpretation ?? 'Result available' }}</p>@endif
                                    </div>
                                @endforeach
                            @empty
                                <p class="text-sm text-slate-500">No laboratory results for this consultation.</p>
                            @endforelse
                        </div>
                    </x-card>
                    <x-card><h3 class="mb-3 font-semibold">Radiology Results</h3><div class="rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">Coming Soon</div></x-card>
                </div>
            @elseif($activeTab === 'plan')
                <x-card><h3 class="mb-3 font-semibold">Doctor Plan</h3><x-textarea wire:model.live.debounce.2000ms="form.clinical_summary" wire:change="autosave" rows="4" placeholder="Doctor notes / clinical summary" /><x-input-error :messages="$errors->get('form.clinical_summary')" class="mt-2" /><x-textarea wire:model.live.debounce.2000ms="form.assessment_notes" wire:change="autosave" rows="4" class="mt-3" placeholder="Assessment notes" /><x-textarea wire:model.live.debounce.2000ms="form.treatment_plan" wire:change="autosave" rows="5" class="mt-3" placeholder="Treatment plan" /><x-textarea wire:model.live.debounce.2000ms="form.discharge_instructions" wire:change="autosave" rows="3" class="mt-3" placeholder="Advice / discharge instructions" /></x-card>
            @elseif($activeTab === 'follow')
                <x-card><h3 class="mb-3 font-semibold">Follow-up Appointment</h3><x-input-label value="Review date and time" /><x-text-input type="datetime-local" wire:model="appointmentForm.scheduled_start" /><x-input-label value="Review reason" class="mt-3" /><x-text-input wire:model="appointmentForm.reason" placeholder="Review reason" /><x-primary-button type="button" wire:click="createFollowUp" class="mt-3">Schedule Review</x-primary-button></x-card>
                <x-card><h3 class="mb-3 font-semibold">Outcome and Disposition</h3><x-select-input wire:model="form.outcome">@foreach($outcomes as $outcome)<option value="{{ $outcome->value }}">{{ str($outcome->value)->replace('_',' ')->title() }}</option>@endforeach</x-select-input><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.follow_up_required"> Follow-up required</label><x-text-input type="date" wire:model="form.follow_up_date" class="mt-3" /></x-card>
            @endif
        </main>

        <aside class="space-y-4">
            <x-card>
                <h3 class="mb-3 font-semibold">Complete Consultation</h3>
                <p class="text-sm text-slate-500">Sign off before completing the consultation.</p>
                <div class="mt-4 flex flex-col gap-2"><x-secondary-button type="button" wire:click="saveDraft">Save Draft</x-secondary-button><x-secondary-button type="button" wire:click="signOff">Sign Off</x-secondary-button><x-primary-button type="button" wire:click="complete">Complete Consultation</x-primary-button><a href="{{ route('clinical-encounters.print', $encounter) }}" class="rounded-md border border-slate-200 px-3 py-2 text-center text-sm dark:border-slate-700">Print Summary</a></div>
            </x-card>

            <x-card>
                <h3 class="mb-3 font-semibold">Active Orders</h3>
                <div class="space-y-3 text-sm">
                    <div><p class="font-medium">Laboratory</p>@forelse($encounter->laboratoryOrders as $order)<p class="text-xs text-slate-500">{{ $order->order_number }} · {{ $statusLabel($order->status) }}</p>@empty<p class="text-xs text-slate-500">None</p>@endforelse</div>
                    <div><p class="font-medium">Procedures</p>@forelse($encounter->procedureOrders as $procedure)<p class="text-xs text-slate-500">{{ $procedure->procedure_name_snapshot }} · {{ $statusLabel($procedure->status) }}</p>@empty<p class="text-xs text-slate-500">None</p>@endforelse</div>
                    <div><p class="font-medium">Medicines</p>@forelse($encounter->prescriptions as $prescription)<p class="text-xs text-slate-500">{{ $prescription->prescription_number }} · {{ $statusLabel($prescription->status) }}</p>@empty<p class="text-xs text-slate-500">None</p>@endforelse</div>
                    <div><p class="font-medium">Referrals</p>@forelse($encounter->referrals as $referral)<p class="text-xs text-slate-500">{{ $referral->destination_facility_name }} · {{ $statusLabel($referral->status) }}</p>@empty<p class="text-xs text-slate-500">None</p>@endforelse</div>
                </div>
            </x-card>
        </aside>
    </div>
</div>
