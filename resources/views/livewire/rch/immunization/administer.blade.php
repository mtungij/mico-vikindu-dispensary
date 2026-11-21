<x-card>
    @if ($linkedEncounter)
        <div class="mb-4">
            <h2 class="font-semibold">{{ $linkedEncounter->patient->fullName() }}</h2>
            <p>{{ $linkedEncounter->patient->patient_number }} · {{ $linkedEncounter->encounter_number }}</p>
            <a class="text-primary" href="{{ route('rch.encounter', $linkedEncounter->visit_id) }}">Open RCH consultation</a>
        </div>
    @endif

    <form wire:submit="save" class="space-y-4">
        <x-input-error :messages="$errors->all()" />
        <fieldset @disabled(!empty($record) || ($linkedEncounter && $linkedEncounter->clinicalEncounter->isReadOnly())) class="grid gap-4 md:grid-cols-2">
            <x-select-input wire:model="form.vaccine_id"><option value="">Select vaccine</option>@foreach($vaccines as $vaccine)<option value="{{ $vaccine->id }}">{{ $vaccine->name }}</option>@endforeach</x-select-input><x-text-input type="date" wire:model="form.administration_date" /><x-select-input wire:model="form.status"><option value="administered">Administered</option><option value="not_given">Not given</option><option value="refused">Refused</option><option value="contraindicated">Contraindicated</option><option value="deferred">Deferred</option></x-select-input><x-text-input wire:model="form.administration_site" placeholder="Site" /><x-textarea wire:model="form.reason_not_given" placeholder="Reason not given" /><x-textarea wire:model="form.notes" placeholder="Notes" />
        </fieldset>
        @if (empty($record) && (! $linkedEncounter || ! $linkedEncounter->clinicalEncounter->isReadOnly()))
            <x-primary-button wire:loading.attr="disabled">Save Administration</x-primary-button>
        @endif
    </form>
</x-card>
