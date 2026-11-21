<x-card>
    @if ($linkedEncounter)
        <div class="mb-4">
            <h2 class="font-semibold">{{ $linkedEncounter->patient->fullName() }}</h2>
            <p>{{ $linkedEncounter->patient->patient_number }} · {{ $linkedEncounter->encounter_number }}</p>
            <a class="text-primary" href="{{ route('rch.encounter', $linkedEncounter->visit_id) }}">Open RCH consultation</a>
        </div>
    @endif
    @if($linkedEncounter && $linkedEncounter->status === 'in_progress')
            @can('rch.immunization.administer')
                <a class="text-primary" href="{{ route('rch.visits.immunization', $linkedEncounter->visit_id) }}">Record immunization for this visit</a>
            @endcan
        @endif
    <form wire:submit="save" class="space-y-4">
        <x-input-error :messages="$errors->all()" />
        <fieldset @disabled(!empty($record) || ($linkedEncounter && $linkedEncounter->clinicalEncounter->isReadOnly())) class="grid gap-4 md:grid-cols-2">
            <x-text-input type="datetime-local" wire:model="form.measured_at" /><x-text-input type="number" step="0.01" wire:model="form.weight_kg" placeholder="Weight kg" /><x-text-input type="number" step="0.01" wire:model="form.length_height_cm" placeholder="Length/height cm" /><x-text-input type="number" step="0.1" wire:model="form.head_circumference_cm" placeholder="Head circumference" /><x-text-input type="number" step="0.1" wire:model="form.muac_cm" placeholder="MUAC cm" /><label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.edema_present" /> Edema present</label><x-textarea wire:model="form.notes" placeholder="Notes" class="md:col-span-2" />
        </fieldset>
        @if (empty($record) && (! $linkedEncounter || ! $linkedEncounter->clinicalEncounter->isReadOnly()))
            <x-primary-button wire:loading.attr="disabled">Save Growth Measurement</x-primary-button>
        @endif
    </form>
</x-card>
