<div class="space-y-6">
    <x-card>
        <div class="grid gap-4 md:grid-cols-4">
            <div><p class="text-xs text-slate-500">Patient</p><p class="font-semibold">{{ $visit->patient->fullName() }}</p><p class="text-sm text-slate-500">{{ $visit->patient->patient_number }}</p></div>
            <div><p class="text-xs text-slate-500">Age/Gender</p><p class="font-semibold">{{ $visit->patient->ageLabel() }} / {{ $visit->patient->gender->value }}</p></div>
            <div><p class="text-xs text-slate-500">Payer</p><p class="font-semibold">{{ $visit->payer_type->value }}</p><p class="text-sm text-slate-500">{{ $visit->visit_type->value }}</p></div>
            <div><p class="text-xs text-slate-500">Invoice</p><p class="font-semibold">{{ $visit->invoice?->invoice_status?->value ?? 'N/A' }}</p></div>
        </div>
        @if($visit->patient->known_allergies || $visit->patient->chronic_conditions)
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-200">Allergies: {{ $visit->patient->known_allergies ?: 'Hakuna zilizorekodiwa' }}</div>
                <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-950/30 dark:text-amber-200">Chronic: {{ $visit->patient->chronic_conditions ?: 'Hakuna zilizorekodiwa' }}</div>
            </div>
        @endif
    </x-card>

    <form wire:submit="complete" class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-card>
                <h3 class="mb-4 font-semibold">Presenting Complaint</h3>
                <x-textarea wire:model.blur="form.chief_complaint_summary" rows="3" placeholder="Malalamiko makuu..." />
            </x-card>
            <x-card>
                <h3 class="mb-4 font-semibold">Vital Signs</h3>
                <div class="grid gap-3 md:grid-cols-4">
                    <x-text-input wire:model.live.debounce.500ms="form.temperature" placeholder="Temperature" />
                    <x-text-input wire:model.live.debounce.500ms="form.systolic_bp" placeholder="Systolic BP" />
                    <x-text-input wire:model.live.debounce.500ms="form.diastolic_bp" placeholder="Diastolic BP" />
                    <x-text-input wire:model.live.debounce.500ms="form.pulse_rate" placeholder="Pulse" />
                    <x-text-input wire:model.live.debounce.500ms="form.respiratory_rate" placeholder="Respiratory rate" />
                    <x-text-input wire:model.live.debounce.500ms="form.oxygen_saturation" placeholder="Oxygen saturation" />
                    <x-text-input wire:model.live.debounce.500ms="form.weight_kg" placeholder="Weight kg" />
                    <x-text-input wire:model.live.debounce.500ms="form.height_cm" placeholder="Height cm" />
                    <x-text-input wire:model.live.debounce.500ms="form.blood_glucose" placeholder="Blood glucose" />
                    <x-text-input wire:model.live.debounce.500ms="form.muac_cm" placeholder="MUAC cm" />
                    <x-text-input wire:model.live.debounce.500ms="form.pain_score" placeholder="Pain 0-10" />
                    <x-select-input wire:model.live="form.consciousness_level"><option value="">Consciousness</option><option value="alert">Alert</option><option value="responds_to_voice">Responds to voice</option><option value="responds_to_pain">Responds to pain</option><option value="unresponsive">Unresponsive</option><option value="confused">Confused</option></x-select-input>
                </div>
                @error('form.pain_score')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </x-card>
            <x-card>
                <h3 class="mb-4 font-semibold">Pregnancy and Danger Signs</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <x-select-input wire:model.live="form.pregnancy_status"><option value="not_applicable">Not applicable</option><option value="not_pregnant">Not pregnant</option><option value="pregnant">Pregnant</option><option value="suspected">Suspected</option><option value="unknown">Unknown</option></x-select-input>
                    <x-text-input wire:model.live="form.gestational_age_weeks" placeholder="Gestational weeks" />
                    <x-select-input wire:model.live="form.infection_risk"><option value="">Infection risk</option><option value="none">None</option><option value="suspected">Suspected</option><option value="confirmed">Confirmed</option></x-select-input>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-2">
                    @foreach($dangerSigns as $sign)
                        <label class="flex items-center gap-2 rounded-md border border-slate-200 p-2 text-sm dark:border-slate-700"><input type="checkbox" wire:model.live="form.danger_signs" value="{{ $sign }}"> {{ $sign }}</label>
                    @endforeach
                </div>
            </x-card>
            <x-card>
                <h3 class="mb-4 font-semibold">Notes</h3>
                <x-textarea wire:model.blur="form.notes" rows="4" />
            </x-card>
        </div>
        <aside class="space-y-4">
            <x-card>
                <p class="text-xs uppercase text-slate-500">Suggested Priority</p>
                <p class="mt-1 text-2xl font-semibold">{{ $suggestedLevel }}</p>
                <x-select-input wire:model="form.triage_level" class="mt-4">@foreach($levels as $level)<option value="{{ $level->value }}">{{ $level->label() }}</option>@endforeach</x-select-input>
            </x-card>
            <x-card>
                <h3 class="mb-3 font-semibold">Clinical Alerts</h3>
                <p class="mb-3 text-xs text-slate-500">{{ config('clinical_reference_ranges.disclaimer') }}</p>
                <div class="space-y-2">@forelse($suggestedAlerts as $alert)<div class="rounded-md border border-amber-200 bg-amber-50 p-2 text-sm dark:border-amber-900 dark:bg-amber-950/30"><p class="font-semibold">{{ $alert['title'] }}</p><p>{{ $alert['message'] }}</p></div>@empty<p class="text-sm text-slate-500">Hakuna alert kwa sasa.</p>@endforelse</div>
            </x-card>
            <div class="flex gap-2"><x-secondary-button type="button" wire:click="saveDraft">Hifadhi Draft</x-secondary-button><x-primary-button type="submit">Kamilisha</x-primary-button></div>
        </aside>
    </form>
</div>
