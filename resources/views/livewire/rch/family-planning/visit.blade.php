<x-card>
    @if ($linkedEncounter)
        <div class="mb-4">
            <h2 class="font-semibold">{{ $linkedEncounter->patient->fullName() }}</h2>
            <p>{{ $linkedEncounter->patient->patient_number }} · {{ $linkedEncounter->encounter_number }}</p>
            <a class="text-primary" href="{{ route('rch.encounter', $linkedEncounter->visit_id) }}">Open RCH consultation</a>
        </div>
    @endif
    @if($linkedEncounter && ! $record && $clients->count() > 1)
            <label>Family planning profile <x-select-input wire:model="clientId"><option value="">Select profile</option>@foreach($clients as $option)<option value="{{ $option->id }}">{{ $option->fp_client_number }}</option>@endforeach</x-select-input></label>
        @endif
    <form wire:submit="save" class="space-y-4">
        <x-input-error :messages="$errors->all()" />
        <fieldset @disabled(!empty($record) || ($linkedEncounter && $linkedEncounter->clinicalEncounter->isReadOnly())) class="grid gap-4 md:grid-cols-2">
            <x-text-input type="date" wire:model="form.visit_date" /><x-select-input wire:model="form.visit_type"><option value="initiation">Initiation</option><option value="follow_up">Follow-up</option><option value="refill">Refill</option><option value="reinjection">Reinjection</option><option value="method_change">Method Change</option><option value="discontinuation">Discontinuation</option></x-select-input><x-select-input wire:model="form.selected_method_id"><option value="">No method change</option>@foreach($methods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</x-select-input><x-text-input type="date" wire:model="form.next_visit_date" /><label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.counselling_done" /> Counselling done</label><x-textarea wire:model="form.discontinuation_reason" placeholder="Discontinuation reason" /><x-textarea wire:model="form.notes" placeholder="Notes" class="md:col-span-2" />
        </fieldset>
        @if (empty($record) && (! $linkedEncounter || ! $linkedEncounter->clinicalEncounter->isReadOnly()))
            <x-primary-button wire:loading.attr="disabled">Save FP Visit</x-primary-button>
        @endif
    </form>
</x-card>
