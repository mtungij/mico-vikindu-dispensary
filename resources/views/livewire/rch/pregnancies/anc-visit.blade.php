<x-card>
    @if ($linkedEncounter)
        <div class="mb-4">
            <h2 class="font-semibold">{{ $linkedEncounter->patient->fullName() }}</h2>
            <p>{{ $linkedEncounter->patient->patient_number }} · {{ $linkedEncounter->encounter_number }}</p>
            <a class="text-primary" href="{{ route('rch.encounter', $linkedEncounter->visit_id) }}">Open RCH consultation</a>
        </div>
    @endif
    @if($linkedEncounter && ! $record && $pregnancies->isEmpty())
            <label>Last menstrual period, if known <x-text-input type="date" wire:model="lmpDate" /></label>
        @elseif($linkedEncounter && ! $record && $pregnancies->count() > 1)
            <label>Pregnancy <x-select-input wire:model="pregnancyId"><option value="">Select pregnancy</option>@foreach($pregnancies as $option)<option value="{{ $option->id }}">{{ $option->pregnancy_number }}</option>@endforeach</x-select-input></label>
        @endif
    <form wire:submit="save" class="space-y-4">
        <x-input-error :messages="$errors->all()" />
        <fieldset @disabled(!empty($record) || ($linkedEncounter && $linkedEncounter->clinicalEncounter->isReadOnly())) class="grid gap-4 md:grid-cols-2">
            <x-text-input type="date" wire:model="form.visit_date" /><x-text-input type="number" step="0.01" wire:model="form.weight_kg" placeholder="Weight kg" /><x-text-input type="number" wire:model="form.systolic_bp" placeholder="Systolic BP" /><x-text-input type="number" wire:model="form.diastolic_bp" placeholder="Diastolic BP" /><x-text-input type="number" step="0.1" wire:model="form.hemoglobin" placeholder="Hemoglobin" /><x-text-input type="number" wire:model="form.fetal_heart_rate" placeholder="Fetal heart rate" /><x-textarea wire:model="form.assessment" placeholder="Assessment" /><x-textarea wire:model="form.plan" placeholder="Plan" /><x-text-input type="date" wire:model="form.next_visit_date" />
        </fieldset>
        @if (empty($record) && (! $linkedEncounter || ! $linkedEncounter->clinicalEncounter->isReadOnly()))
            <x-primary-button wire:loading.attr="disabled">Save ANC Visit</x-primary-button>
        @endif
    </form>
</x-card>
