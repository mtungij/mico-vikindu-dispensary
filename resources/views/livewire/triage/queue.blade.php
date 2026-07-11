<div class="space-y-6" wire:poll.30s>
    <div class="grid gap-3 md:grid-cols-6">
        <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta mgonjwa..." class="md:col-span-2" />
        <x-select-input wire:model.live="priority"><option value="">Priority zote</option><option value="emergency">Emergency</option><option value="urgent">Urgent</option><option value="normal">Normal</option></x-select-input>
        <x-select-input wire:model.live="payerType"><option value="">Payer zote</option><option value="cash">Cash</option><option value="insurance">Insurance</option><option value="corporate">Corporate</option></x-select-input>
        <x-select-input wire:model.live="visitType"><option value="">Visit zote</option><option value="outpatient">Outpatient</option><option value="emergency">Emergency</option><option value="follow_up">Follow-up</option></x-select-input>
        <x-select-input wire:model.live="department"><option value="">Departments</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</x-select-input>
    </div>

    <x-card>
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Queue</th><th>Patient</th><th>Age/Gender</th><th>Visit</th><th>Payer</th><th>Arrival</th><th>Priority</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($visits as $visit)
                        @php($queue = $queues->get($visit->id))
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $queue?->queue_number ?? '-' }}</td>
                            <td><a href="{{ route('patients.show', $visit->patient) }}" class="font-medium text-primary">{{ $visit->patient->fullName() }}</a><div class="text-xs text-slate-500">{{ $visit->patient->patient_number }}</div></td>
                            <td>{{ $visit->patient->ageLabel() }} / {{ $visit->patient->gender->value }}</td>
                            <td>{{ $visit->visit_type->value }}</td>
                            <td>{{ $visit->payer_type->value }}</td>
                            <td>{{ $visit->registered_at?->diffForHumans() }}</td>
                            <td>{{ $visit->priority->value }}</td>
                            <td>{{ $visit->visit_status->value }}</td>
                            <td class="text-right">
                                <a href="{{ route('triage.assessment', $visit) }}" class="inline-flex rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Anza Triage"><x-lucide-heart-pulse class="h-4 w-4" /></a>
                                <button wire:click="markEmergency({{ $visit->id }})" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Mark emergency"><x-lucide-triangle-alert class="h-4 w-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-10 text-center text-slate-500">Hakuna mgonjwa anayesubiri triage.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="space-y-3 md:hidden">
            @foreach($visits as $visit)
                <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $visit->patient->fullName() }}</p><p class="text-xs text-slate-500">{{ $visit->patient->patient_number }} · {{ $visit->patient->ageLabel() }}</p></div><span class="text-xs font-semibold">{{ $visit->priority->value }}</span></div>
                    <div class="mt-3 flex items-center justify-between text-sm"><span>{{ $visit->payer_type->value }}</span><a href="{{ route('triage.assessment', $visit) }}" class="rounded-md bg-primary px-3 py-2 text-white">Anza</a></div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $visits->links() }}</div>
    </x-card>
</div>
