<div class="space-y-6">
    <x-card>
        <div class="grid gap-3 md:grid-cols-4">
            <div>
                <p class="text-xs text-slate-500">Patient</p>
                <p class="font-semibold">{{ $laboratoryResult->order->patient?->fullName() }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Test</p>
                <p>{{ $laboratoryResult->test->name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Entered</p>
                <p>{{ $laboratoryResult->entered_at?->format('d/m/Y H:i') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Status</p>
                <p>{{ $laboratoryResult->result_status->value }}</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-slate-500">
                    <th class="py-3">Parameter</th>
                    <th>Result</th>
                    <th>Range</th>
                    <th>Flag</th>
                </tr>
            </thead>
            <tbody>
                @foreach($laboratoryResult->values as $value)
                    <tr class="border-t border-slate-100 dark:border-slate-800">
                        <td class="py-3">{{ $value->parameter_name_snapshot }}</td>
                        <td>{{ $value->numeric_value ?? $value->selected_value ?? $value->text_value }}</td>
                        <td>{{ $value->reference_range_snapshot }}</td>
                        <td>{{ $value->abnormal_flag?->value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4">
            @if($laboratoryResult->result_status->value === 'pending_verification')
                <div class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
                    <x-text-input wire:model="returnReason" placeholder="Return reason" />
                    <x-secondary-button wire:click="returnForCorrection">Return</x-secondary-button>
                    <x-primary-button wire:click="verify" wire:loading.attr="disabled" wire:target="verify">
                        Verify
                    </x-primary-button>
                </div>
            @elseif($laboratoryResult->result_status->value === 'verified')
                <div class="flex items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        Result is verified but not yet released. The final report is unavailable until every result is released.
                    </p>
                    @can('release', $laboratoryResult)
                        <x-primary-button wire:click="release" wire:loading.attr="disabled" wire:target="release">
                            <span wire:loading.remove wire:target="release">Release Results</span>
                            <span wire:loading wire:target="release">Releasing...</span>
                        </x-primary-button>
                    @endcan
                </div>
            @endif
        </div>
    </x-card>

    @if($reportEligible)
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-semibold">Final report is ready.</p>
                <div class="flex flex-wrap gap-2">
                    @can('viewReport', $laboratoryResult->order)
                        <a href="{{ route('laboratory.orders.report.view', $laboratoryResult->order) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <x-lucide-eye class="h-4 w-4" /> Angalia Majibu
                        </a>
                    @endcan
                    @can('downloadReport', $laboratoryResult->order)
                        <a href="{{ route('laboratory.orders.report.download', $laboratoryResult->order) }}" class="inline-flex items-center gap-1 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">
                            <x-lucide-download class="h-4 w-4" /> Pakua Majibu
                        </a>
                    @endcan
                    @can('printReport', $laboratoryResult->order)
                        <a href="{{ route('laboratory.orders.report.print', $laboratoryResult->order) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <x-lucide-printer class="h-4 w-4" /> Chapisha Majibu
                        </a>
                    @endcan
                </div>
            </div>
        </x-card>
    @endif
</div>
