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

    <form wire:submit.prevent="complete" class="grid gap-6 xl:grid-cols-[1fr_22rem]" novalidate>
        <div class="space-y-6">
            @if($errors->any())
                <div role="alert" aria-live="assertive" class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    <p class="font-semibold">Hatukuweza kukamilisha Triage.</p>
                    <p class="mt-1 text-sm">Tafadhali rekebisha sehemu zifuatazo:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-card>
                <h3 class="mb-4 font-semibold">Presenting Complaint</h3>
                <div data-field="chief_complaint_summary">
                    <x-textarea name="chief_complaint_summary" wire:model.blur="form.chief_complaint_summary" rows="3" placeholder="Malalamiko makuu..." aria-invalid="{{ $errors->has('form.chief_complaint_summary') ? 'true' : 'false' }}" :class="$errors->has('form.chief_complaint_summary') ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : ''" />
                    @error('form.chief_complaint_summary')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </x-card>

            <x-card>
                <h3 class="mb-4 font-semibold">Vital Signs</h3>
                <div class="grid gap-3 md:grid-cols-4">
                    <div data-field="temperature"><x-text-input name="temperature" wire:model.live.debounce.500ms="form.temperature" placeholder="Temperature °C" :class="$errors->has('form.temperature') ? 'border-red-500' : ''" />@error('form.temperature')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="systolic_bp"><x-text-input name="systolic_bp" wire:model.live.debounce.500ms="form.systolic_bp" placeholder="Systolic BP" :class="$errors->has('form.systolic_bp') ? 'border-red-500' : ''" />@error('form.systolic_bp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="diastolic_bp"><x-text-input name="diastolic_bp" wire:model.live.debounce.500ms="form.diastolic_bp" placeholder="Diastolic BP" :class="$errors->has('form.diastolic_bp') ? 'border-red-500' : ''" />@error('form.diastolic_bp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="pulse_rate"><x-text-input name="pulse_rate" wire:model.live.debounce.500ms="form.pulse_rate" placeholder="Pulse / min" :class="$errors->has('form.pulse_rate') ? 'border-red-500' : ''" />@error('form.pulse_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="respiratory_rate"><x-text-input name="respiratory_rate" wire:model.live.debounce.500ms="form.respiratory_rate" placeholder="Respiratory rate" :class="$errors->has('form.respiratory_rate') ? 'border-red-500' : ''" />@error('form.respiratory_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="oxygen_saturation"><x-text-input name="oxygen_saturation" wire:model.live.debounce.500ms="form.oxygen_saturation" placeholder="Oxygen saturation %" :class="$errors->has('form.oxygen_saturation') ? 'border-red-500' : ''" />@error('form.oxygen_saturation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="weight_kg"><x-text-input name="weight_kg" wire:model.live.debounce.500ms="form.weight_kg" placeholder="Weight kg" :class="$errors->has('form.weight_kg') ? 'border-red-500' : ''" />@error('form.weight_kg')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="height_cm"><x-text-input name="height_cm" wire:model.live.debounce.500ms="form.height_cm" placeholder="Height cm" :class="$errors->has('form.height_cm') ? 'border-red-500' : ''" />@error('form.height_cm')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="blood_glucose"><x-text-input name="blood_glucose" wire:model.live.debounce.500ms="form.blood_glucose" placeholder="Blood glucose" :class="$errors->has('form.blood_glucose') ? 'border-red-500' : ''" />@error('form.blood_glucose')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="muac_cm"><x-text-input name="muac_cm" wire:model.live.debounce.500ms="form.muac_cm" placeholder="MUAC cm" :class="$errors->has('form.muac_cm') ? 'border-red-500' : ''" />@error('form.muac_cm')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="pain_score"><x-text-input name="pain_score" wire:model.live.debounce.500ms="form.pain_score" placeholder="Pain 0-10" :class="$errors->has('form.pain_score') ? 'border-red-500' : ''" />@error('form.pain_score')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="consciousness_level"><x-select-input name="consciousness_level" wire:model.live="form.consciousness_level" :class="$errors->has('form.consciousness_level') ? 'border-red-500' : ''"><option value="">Consciousness</option><option value="alert">Alert</option><option value="responds_to_voice">Responds to voice</option><option value="responds_to_pain">Responds to pain</option><option value="unresponsive">Unresponsive</option><option value="confused">Confused</option></x-select-input>@error('form.consciousness_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                </div>
            </x-card>

            <x-card>
                <h3 class="mb-4 font-semibold">Pregnancy and Clinical Risks</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <div data-field="pregnancy_status"><x-select-input name="pregnancy_status" wire:model.live="form.pregnancy_status" :class="$errors->has('form.pregnancy_status') ? 'border-red-500' : ''"><option value="not_applicable">Not applicable</option><option value="not_pregnant">Not pregnant</option><option value="pregnant">Pregnant</option><option value="suspected">Suspected</option><option value="unknown">Unknown</option></x-select-input>@error('form.pregnancy_status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="gestational_age_weeks"><x-text-input name="gestational_age_weeks" wire:model.live="form.gestational_age_weeks" placeholder="Gestational weeks" :class="$errors->has('form.gestational_age_weeks') ? 'border-red-500' : ''" />@error('form.gestational_age_weeks')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="infection_risk"><x-select-input name="infection_risk" wire:model.live="form.infection_risk" :class="$errors->has('form.infection_risk') ? 'border-red-500' : ''"><option value="">Infection risk</option><option value="none">None</option><option value="suspected">Suspected</option><option value="confirmed">Confirmed</option></x-select-input>@error('form.infection_risk')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div data-field="fall_risk"><x-select-input name="fall_risk" wire:model.live="form.fall_risk" :class="$errors->has('form.fall_risk') ? 'border-red-500' : ''"><option value="">Fall risk</option><option value="none">None</option><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option></x-select-input>@error('form.fall_risk')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                </div>

                <div data-field="danger_signs" class="mt-4">
                    <p class="mb-2 text-sm font-medium">Danger signs</p>
                    <div class="grid gap-2 md:grid-cols-2">
                        @foreach($dangerSigns as $sign)
                            <label class="flex items-center gap-2 rounded-md border border-slate-200 p-2 text-sm dark:border-slate-700"><input type="checkbox" wire:model.live="form.danger_signs" value="{{ $sign }}"> {{ $sign }}</label>
                        @endforeach
                    </div>
                    @error('form.danger_signs')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div data-field="allergies_confirmed" class="mt-4">
                    <label @class(['flex items-start gap-2 rounded-md border p-3 text-sm', 'border-red-500 bg-red-50 dark:bg-red-950/30' => $errors->has('form.allergies_confirmed'), 'border-slate-200 dark:border-slate-700' => ! $errors->has('form.allergies_confirmed')])>
                        <input name="allergies_confirmed" type="checkbox" wire:model="form.allergies_confirmed" class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary">
                        <span>Nimethibitisha taarifa za mzio wa mgonjwa, ikiwemo kama hana mzio unaojulikana.</span>
                    </label>
                    @error('form.allergies_confirmed')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </x-card>

            <x-card>
                <h3 class="mb-3 font-semibold">Notes</h3>
                <div data-field="notes"><x-textarea name="notes" wire:model.blur="form.notes" rows="4" :class="$errors->has('form.notes') ? 'border-red-500' : ''" />@error('form.notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            </x-card>
        </div>

        <aside class="space-y-4">
            <x-card>
                <p class="text-xs uppercase text-slate-500">Suggested Priority</p>
                <p class="mt-1 text-2xl font-semibold">{{ $suggestedLevel }}</p>
                <div data-field="triage_level">
                    <x-select-input name="triage_level" wire:model="form.triage_level" :class="$errors->has('form.triage_level') ? 'mt-4 border-red-500' : 'mt-4'">@foreach($levels as $level)<option value="{{ $level->value }}">{{ $level->label() }}</option>@endforeach</x-select-input>
                    @error('form.triage_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </x-card>

            <x-card>
                <h3 class="mb-3 font-semibold">Clinical Alerts</h3>
                <p class="mb-3 text-xs text-slate-500">{{ config('clinical_reference_ranges.disclaimer') }}</p>
                <div class="space-y-2">@forelse($suggestedAlerts as $alert)<div class="rounded-md border border-amber-200 bg-amber-50 p-2 text-sm dark:border-amber-900 dark:bg-amber-950/30"><p class="font-semibold">{{ $alert['title'] }}</p><p>{{ $alert['message'] }}</p></div>@empty<p class="text-sm text-slate-500">Hakuna alert kwa sasa.</p>@endforelse</div>
            </x-card>

            @if($assessment?->status === \App\Enums\TriageStatus::Completed)
                <div class="rounded-md bg-emerald-100 px-4 py-3 text-center text-sm font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">Triage Imekamilika</div>
            @else
                <div class="flex gap-2">
                    <x-secondary-button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,complete">Hifadhi Draft</x-secondary-button>
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="complete">
                        <span wire:loading.remove wire:target="complete">Kamilisha Triage</span>
                        <span wire:loading wire:target="complete">Inakamilisha...</span>
                    </x-primary-button>
                </div>
            @endif
        </aside>
    </form>

    @script
        <script>
            $wire.on('triage-validation-failed', ({ field }) => {
                requestAnimationFrame(() => {
                    const container = document.querySelector(`[data-field="${field}"]`);
                    const input = container?.matches('input, select, textarea')
                        ? container
                        : container?.querySelector('input, select, textarea');

                    (input ?? container)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    input?.focus({ preventScroll: true });
                });
            });
        </script>
    @endscript
</div>
