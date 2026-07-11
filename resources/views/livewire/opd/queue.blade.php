<div class="space-y-6" wire:poll.30s>
    <div class="grid gap-3 md:grid-cols-4">
        <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta mgonjwa..." class="md:col-span-2" />
        <x-select-input wire:model.live="priority"><option value="">Priority zote</option><option value="emergency">Emergency</option><option value="urgent">Urgent</option><option value="normal">Normal</option></x-select-input>
        <x-select-input wire:model.live="payerType"><option value="">Payer zote</option><option value="cash">Cash</option><option value="insurance">Insurance</option><option value="corporate">Corporate</option></x-select-input>
    </div>
    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Queue</th><th>Patient</th><th>Age/Gender</th><th>Triage</th><th>Abnormal Vitals</th><th>Payer</th><th>Waiting</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($visits as $visit)
                        @php($triage = $visit->latestTriageAssessment)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $queues->get($visit->id)?->queue_number ?? '-' }}</td>
                            <td><a href="{{ route('patients.show', $visit->patient) }}" class="font-medium text-primary">{{ $visit->patient->fullName() }}</a><div class="text-xs text-slate-500">{{ $visit->patient->patient_number }}</div></td>
                            <td>{{ $visit->patient->ageLabel() }} / {{ $visit->patient->gender->value }}</td>
                            <td>{{ $triage?->triage_level?->value ?? '-' }}</td>
                            <td class="max-w-xs text-xs">{{ collect([$triage?->temperature ? 'T '.$triage->temperature : null, $triage?->oxygen_saturation ? 'SpO2 '.$triage->oxygen_saturation : null, $triage?->systolic_bp ? 'BP '.$triage->systolic_bp.'/'.$triage->diastolic_bp : null])->filter()->implode(' · ') }}</td>
                            <td>{{ $visit->payer_type->value }}</td>
                            <td>{{ $visit->registered_at?->diffForHumans() }}</td>
                            <td>{{ $visit->visit_status->value }}</td>
                            <td class="text-right"><button wire:click="startConsultation({{ $visit->id }})" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Start/Resume"><x-lucide-stethoscope class="h-4 w-4" /></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-10 text-center text-slate-500">Hakuna mgonjwa OPD queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $visits->links() }}</div>
    </x-card>
</div>
