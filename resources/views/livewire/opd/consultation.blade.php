<div class="space-y-6">
    <div class="sticky top-16 z-20 rounded-md border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-card-dark">
        <div class="grid gap-3 md:grid-cols-5">
            <div><p class="text-xs text-slate-500">Patient</p><p class="font-semibold">{{ $visit->patient->fullName() }}</p><p class="text-xs text-slate-500">{{ $visit->patient->patient_number }}</p></div>
            <div><p class="text-xs text-slate-500">Age/Gender</p><p class="font-semibold">{{ $visit->patient->ageLabel() }} / {{ $visit->patient->gender->value }}</p></div>
            <div><p class="text-xs text-slate-500">Visit</p><p class="font-semibold">{{ $visit->visit_number }}</p><p class="text-xs text-slate-500">{{ $visit->payer_type->value }}</p></div>
            <div><p class="text-xs text-slate-500">Triage</p><p class="font-semibold">{{ $visit->latestTriageAssessment?->triage_level?->value ?? '-' }}</p><p class="text-xs text-slate-500">BP {{ $visit->latestTriageAssessment?->systolic_bp ?? '-' }}/{{ $visit->latestTriageAssessment?->diastolic_bp ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-500">Encounter</p><p class="font-semibold">{{ $encounter->encounter_number }}</p><p class="text-xs text-slate-500">{{ $encounter->status->value }} · {{ $saveState }}</p></div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[18rem_1fr_20rem]">
        <aside class="space-y-4">
            <x-card><h3 class="mb-3 font-semibold">Patient Summary</h3><p class="text-sm">Allergies: {{ $visit->patient->known_allergies ?: 'Hakuna' }}</p><p class="mt-2 text-sm">Chronic: {{ $visit->patient->chronic_conditions ?: 'Hakuna' }}</p></x-card>
            <x-card><h3 class="mb-3 font-semibold">Clinical Timeline</h3><livewire:clinical.patient-timeline :patient="$visit->patient" /></x-card>
        </aside>

        <main class="space-y-6">
            <div class="flex flex-wrap gap-2">
                @foreach(['summary' => 'Muhtasari', 'history' => 'History', 'exam' => 'Examination', 'diagnoses' => 'Diagnoses', 'orders' => 'Orders', 'plan' => 'Plan', 'follow' => 'Follow-up/Referral'] as $key => $label)
                    <button type="button" wire:click="$set('activeTab','{{ $key }}')" class="rounded-md px-3 py-2 text-sm {{ $activeTab === $key ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800' }}">{{ $label }}</button>
                @endforeach
            </div>

            @if($activeTab === 'summary')
                <x-card><h3 class="mb-3 font-semibold">Doctor Notes</h3><x-textarea wire:model.live.debounce.2000ms="form.clinical_summary" wire:change="autosave" rows="8" placeholder="Clinical summary..." /><x-input-error :messages="$errors->get('form.clinical_summary')" class="mt-2" /></x-card>
            @elseif($activeTab === 'history')
                <x-card>
                    <div class="grid gap-4">
                        <x-textarea wire:model.live.debounce.2000ms="form.chief_complaint" wire:change="autosave" rows="2" placeholder="Chief complaint" />
                        <x-textarea wire:model.live.debounce.2000ms="form.history_of_presenting_illness" wire:change="autosave" rows="5" placeholder="History of presenting illness" />
                        <x-textarea wire:model.live.debounce.2000ms="form.past_medical_history" wire:change="autosave" rows="3" placeholder="Past medical history" />
                        <x-textarea wire:model.live.debounce.2000ms="form.medication_history" wire:change="autosave" rows="3" placeholder="Medication history" />
                    </div>
                </x-card>
                <x-card><h3 class="mb-3 font-semibold">Add Complaint</h3><div class="grid gap-3 md:grid-cols-2"><x-text-input wire:model="complaintForm.complaint" placeholder="Complaint" /><x-select-input wire:model="complaintForm.severity"><option value="">Severity</option><option value="mild">Mild</option><option value="moderate">Moderate</option><option value="severe">Severe</option></x-select-input><x-text-input wire:model="complaintForm.duration_value" placeholder="Duration" /><x-select-input wire:model="complaintForm.duration_unit"><option value="">Unit</option><option value="hours">Hours</option><option value="days">Days</option><option value="weeks">Weeks</option><option value="months">Months</option></x-select-input></div><x-primary-button type="button" wire:click="addComplaint" class="mt-3">Ongeza</x-primary-button></x-card>
            @elseif($activeTab === 'exam')
                <x-card><h3 class="mb-3 font-semibold">Physical Examination</h3><div class="grid gap-3 md:grid-cols-3"><x-select-input wire:model="examForm.examination_system"><option value="general">General</option><option value="cardiovascular">Cardiovascular</option><option value="respiratory">Respiratory</option><option value="gastrointestinal">Gastrointestinal</option><option value="neurological">Neurological</option><option value="musculoskeletal">Musculoskeletal</option><option value="skin">Skin</option><option value="ent">ENT</option><option value="eyes">Eyes</option><option value="oral">Oral</option><option value="obstetric">Obstetric</option><option value="other">Other</option></x-select-input><x-select-input wire:model="examForm.status"><option value="normal">Normal</option><option value="abnormal">Abnormal</option><option value="not_examined">Not examined</option></x-select-input></div><x-textarea wire:model="examForm.findings" rows="5" class="mt-3" placeholder="Findings" /><x-primary-button type="button" wire:click="saveExamination" class="mt-3">Hifadhi</x-primary-button></x-card>
                <x-card><div class="space-y-3">@foreach($encounter->examinations as $exam)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-semibold">{{ $exam->examination_system }} · {{ $exam->status }}</p><p class="text-sm">{{ $exam->findings }}</p></div>@endforeach</div></x-card>
            @elseif($activeTab === 'diagnoses')
                <x-card><h3 class="mb-3 font-semibold">Add Diagnosis</h3><livewire:clinical.icd10-search /><div class="mt-3 grid gap-3 md:grid-cols-2"><x-text-input wire:model="diagnosisForm.icd10_code" placeholder="ICD-10 code" /><x-text-input wire:model="diagnosisForm.diagnosis_name" placeholder="Diagnosis name" /><x-select-input wire:model="diagnosisForm.diagnosis_type"><option value="provisional">Provisional</option><option value="differential">Differential</option><option value="final">Final</option><option value="confirmed">Confirmed</option><option value="rule_out">Rule out</option></x-select-input><x-select-input wire:model="diagnosisForm.certainty"><option value="suspected">Suspected</option><option value="probable">Probable</option><option value="confirmed">Confirmed</option></x-select-input></div><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="diagnosisForm.is_primary"> Primary diagnosis</label><x-primary-button type="button" wire:click="addDiagnosis" class="mt-3">Ongeza Diagnosis</x-primary-button></x-card>
                <x-card><div class="space-y-3">@foreach($encounter->diagnoses as $diagnosis)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-semibold">{{ $diagnosis->diagnosis_name }} @if($diagnosis->is_primary)<span class="text-xs text-primary">Primary</span>@endif</p><p class="text-xs text-slate-500">{{ $diagnosis->diagnosis_type->value }} · {{ $diagnosis->certainty->value }} · {{ $diagnosis->icd10_code }}</p></div>@endforeach</div></x-card>
            @elseif($activeTab === 'orders')
                <x-card><h3 class="mb-3 font-semibold">Lab Order</h3><x-select-input wire:model="labForm.service_ids" multiple>@foreach($labServices as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</x-select-input><x-textarea wire:model="labForm.clinical_notes" rows="2" class="mt-3" placeholder="Clinical notes" /><x-primary-button type="button" wire:click="addLabOrder" class="mt-3">Order Lab</x-primary-button></x-card>
                <x-card><h3 class="mb-3 font-semibold">Prescription</h3><div class="grid gap-3 md:grid-cols-2"><x-text-input wire:model="prescriptionItemForm.medication_name" placeholder="Medication" /><x-text-input wire:model="prescriptionItemForm.dose" placeholder="Dose" /><x-text-input wire:model="prescriptionItemForm.frequency" placeholder="Frequency" /><x-text-input wire:model="prescriptionItemForm.duration_value" placeholder="Duration" /></div><x-primary-button type="button" wire:click="addPrescription" class="mt-3">Prescribe</x-primary-button></x-card>
                <x-card><h3 class="mb-3 font-semibold">Procedure</h3><x-select-input wire:model="procedureForm.service_id"><option value="">Chagua service</option>@foreach($procedureServices as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</x-select-input><x-text-input wire:model="procedureForm.procedure_name_snapshot" placeholder="Au andika procedure" class="mt-3" /><x-primary-button type="button" wire:click="addProcedure" class="mt-3">Order Procedure</x-primary-button></x-card>
            @elseif($activeTab === 'plan')
                <x-card><x-textarea wire:model.live.debounce.2000ms="form.assessment_notes" wire:change="autosave" rows="4" placeholder="Assessment notes" /><x-textarea wire:model.live.debounce.2000ms="form.treatment_plan" wire:change="autosave" rows="5" class="mt-3" placeholder="Treatment plan" /><x-textarea wire:model.live.debounce.2000ms="form.discharge_instructions" wire:change="autosave" rows="3" class="mt-3" placeholder="Discharge instructions" /></x-card>
            @elseif($activeTab === 'follow')
                <x-card><h3 class="mb-3 font-semibold">Follow-up</h3><x-text-input type="datetime-local" wire:model="appointmentForm.scheduled_start" /><x-text-input wire:model="appointmentForm.reason" placeholder="Reason" class="mt-3" /><x-primary-button type="button" wire:click="createFollowUp" class="mt-3">Create Follow-up</x-primary-button></x-card>
                <x-card><h3 class="mb-3 font-semibold">Referral</h3><x-text-input wire:model="referralForm.destination_facility_name" placeholder="Destination facility" /><x-textarea wire:model="referralForm.reason" rows="3" class="mt-3" placeholder="Reason" /><x-select-input wire:model="referralForm.urgency" class="mt-3"><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></x-select-input><x-primary-button type="button" wire:click="createReferral" class="mt-3">Prepare Referral</x-primary-button></x-card>
            @endif
        </main>

        <aside class="space-y-4">
            <x-card>
                <h3 class="mb-3 font-semibold">Actions</h3>
                <x-select-input wire:model="form.outcome">@foreach($outcomes as $outcome)<option value="{{ $outcome->value }}">{{ str($outcome->value)->replace('_',' ')->title() }}</option>@endforeach</x-select-input>
                <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.follow_up_required"> Follow-up required</label>
                <x-text-input type="date" wire:model="form.follow_up_date" class="mt-3" />
                <div class="mt-4 flex flex-col gap-2"><x-secondary-button type="button" wire:click="saveDraft">Hifadhi Draft</x-secondary-button><x-secondary-button type="button" wire:click="signOff">Sign Off</x-secondary-button><x-primary-button type="button" wire:click="complete">Kamilisha</x-primary-button><a href="{{ route('clinical-encounters.print', $encounter) }}" class="rounded-md border border-slate-200 px-3 py-2 text-center text-sm dark:border-slate-700">Print Summary</a></div>
            </x-card>
            <x-card><h3 class="mb-3 font-semibold">Orders</h3><p class="text-sm">Lab: {{ $encounter->laboratoryOrders->count() }}</p><p class="text-sm">Rx: {{ $encounter->prescriptions->count() }}</p><p class="text-sm">Procedures: {{ $encounter->procedureOrders->count() }}</p></x-card>
        </aside>
    </div>
</div>
