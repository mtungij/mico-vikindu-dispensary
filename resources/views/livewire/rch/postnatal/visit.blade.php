<div class="space-y-5">
    <x-card>
        <h2 class="text-lg font-semibold">PNC Visit · {{ $encounter->patient->fullName() }}</h2>
        <p>{{ $encounter->patient->patient_number }} · {{ $encounter->patient->ageLabel() }} · {{ $encounter->encounter_number }}</p>
        <p>{{ str($record?->status ?? 'draft')->headline() }} @if($record?->provider) · {{ $record->provider->name }} @endif</p>
        <a class="text-primary" href="{{ route('rch.encounter', $encounter->visit_id) }}">RCH consultation, orders and appointments</a>
        @if($encounter->follow_up_date)
            <p>Booked follow-up · {{ $encounter->follow_up_date->format('d M Y') }}</p>
        @endif
    </x-card>

    <x-card>
        <form wire:submit="save" class="space-y-4">
            <x-input-error :messages="$errors->all()" />
            <fieldset @disabled(!$editable) class="grid gap-4 sm:grid-cols-2">
                @foreach (['visit_date' => 'PNC visit date', 'delivery_date' => 'Delivery date', 'follow_up_date' => 'Planned follow-up date'] as $field => $label)
                    <label>{{ $label }} <x-text-input class="w-full" type="date" wire:model="form.{{ $field }}" /></label>
                @endforeach
                <h3 class="font-semibold sm:col-span-2">Maternal vital signs</h3>
                @foreach (\App\Livewire\Forms\Rch\PncVisitForm::VITALS as $field => $label)
                    <label>{{ $label }} <x-text-input class="w-full" type="number" step="any" wire:model="form.maternal_vitals.{{ $field }}" /></label>
                @endforeach
                @foreach (\App\Livewire\Forms\Rch\PncVisitForm::NOTES as $field => $label)
                    <label>{{ $label }} <x-textarea class="w-full" rows="3" wire:model="form.{{ $field }}" /></label>
                @endforeach
            </fieldset>
            @if ($editable)
                <x-primary-button wire:loading.attr="disabled">Save PNC visit</x-primary-button>
            @endif
        </form>
    </x-card>

    <x-card>
        <h3 class="mb-3 font-semibold">PNC history</h3>
        @forelse ($history as $previous)
            <p class="py-1" wire:key="pnc-history-{{ $previous->id }}">
                <a class="text-primary" href="{{ route('rch.visits.pnc', $previous->visit_id) }}">{{ $previous->visit_date->format('d M Y') }}</a>
                · {{ str($previous->status)->headline() }} @if($previous->provider) · {{ $previous->provider->name }} @endif
            </p>
        @empty
            <p>No PNC visits recorded.</p>
        @endforelse
    </x-card>
</div>
