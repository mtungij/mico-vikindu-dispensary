<div class="space-y-6">
    <x-card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-4">
                <div><p class="text-xs text-slate-500">Patient</p><p class="font-semibold">{{ $order->patient?->fullName() }}</p></div>
                <div><p class="text-xs text-slate-500">Payment</p><p>{{ $order->payment_status->value }}</p></div>
                <div><p class="text-xs text-slate-500">Status</p><p>{{ $order->status->value }}</p></div>
                <div><p class="text-xs text-slate-500">Ordered</p><p>{{ $order->ordered_at?->format('d/m/Y H:i') }}</p></div>
            </div>
            @if($reportEligible)
                <div class="flex flex-wrap gap-2">
                    @can('viewReport', $order)
                        <a href="{{ route('laboratory.orders.report.view', $order) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-sm"><x-lucide-eye class="h-4 w-4" /> Angalia Majibu</a>
                    @endcan
                    @can('downloadReport', $order)
                        <a href="{{ route('laboratory.orders.report.download', $order) }}" class="inline-flex items-center gap-1 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white"><x-lucide-download class="h-4 w-4" /> Pakua Majibu</a>
                    @endcan
                    @can('printReport', $order)
                        <a href="{{ route('laboratory.orders.report.print', $order) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-sm"><x-lucide-printer class="h-4 w-4" /> Chapisha Majibu</a>
                    @endcan
                </div>
            @endif
        </div>
    </x-card>

    <div class="flex gap-2">
        @foreach(['summary' => 'Summary', 'tests' => 'Tests', 'samples' => 'Samples', 'results' => 'Results', 'billing' => 'Billing', 'alerts' => 'Critical Alerts', 'audit' => 'Audit Timeline'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm {{ $tab === $key ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800' }}">{{ $label }}</button>
        @endforeach
    </div>

    <x-card>
        @if($tab === 'tests')
            <div class="space-y-3">
                @foreach($order->items as $item)
                    <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <strong>{{ $item->test_name_snapshot }}</strong>
                        <p class="text-sm text-slate-500">Status: {{ $item->status }} · Payment: {{ $item->invoiceItem?->paid_amount > 0 ? 'Paid/partial' : ($item->invoiceItem?->status ?? 'No charge') }} · Result: {{ $item->result_status ?? 'Pending' }}</p>
                        @if($item->terminal_decided_at)
                            <p class="mt-1 text-xs text-slate-500">{{ \App\Services\LaboratoryOrderItemDecisionService::REASONS[$item->terminal_reason_code] ?? $item->terminal_reason_code }}{{ $item->terminal_reason ? ': '.$item->terminal_reason : '' }} · {{ $item->terminalDecider?->name }} · {{ $item->terminal_decided_at->format('d/m/Y H:i') }}</p>
                        @elseif(! in_array($item->status, ['completed', 'cancelled', 'not_performed', 'entered_in_error'], true) && ! in_array($item->result_status, ['pending_verification', 'verified', 'released'], true) && $item->results->isEmpty())
                            @can('decideItem', $order)
                                <div class="mt-2 flex gap-2">
                                    <button type="button" wire:click="beginDecision({{ $item->id }}, 'cancelled')" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700">Cancel Test</button>
                                    <button type="button" wire:click="beginDecision({{ $item->id }}, 'not_performed')" class="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700">Mark Not Performed</button>
                                </div>
                            @endcan
                        @endif
                        @if($decidingItemId === $item->id)
                            <form wire:submit="decide" class="mt-3 space-y-2 rounded bg-slate-50 p-3 dark:bg-slate-800">
                                <p class="text-sm font-semibold">{{ $decision === 'cancelled' ? 'Cancel Test' : 'Mark Not Performed' }}</p>
                                <select wire:model="reasonCode" class="w-full rounded border-slate-300 text-sm" required>
                                    <option value="">Select reason</option>
                                    @foreach(\App\Services\LaboratoryOrderItemDecisionService::REASONS as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('reasonCode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <textarea wire:model="reasonDetail" class="w-full rounded border-slate-300 text-sm" placeholder="Additional detail where required"></textarea>
                                @error('reasonDetail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('reason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('item') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="flex gap-2"><button type="submit" class="rounded bg-primary px-3 py-1 text-sm text-white">Confirm decision</button><button type="button" wire:click="$set('decidingItemId', null)" class="text-sm">Back</button></div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif($tab === 'samples')
            <div class="space-y-3">
                @foreach($order->samples as $sample)
                    <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">{{ $sample->sample_number }} · {{ $sample->specimenType?->name }} · {{ $sample->sample_status->value }}</div>
                @endforeach
            </div>
        @elseif($tab === 'results')
            <div class="space-y-3">
                @foreach($order->results as $result)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <a href="{{ route('laboratory.results.print', $result) }}" class="block">{{ $result->test?->name }} · {{ $result->result_status->value }} · {{ $result->abnormal_flag?->value }}</a>
                        @if($result->result_status->value === 'pending_verification' && auth()->user()->can('laboratory-results.verify'))
                            <a href="{{ route('laboratory.results.verify', $result) }}" class="text-sm font-semibold text-primary">Verify Results</a>
                        @elseif($result->result_status->value === 'verified' && auth()->user()->can('laboratory-results.release'))
                            <a href="{{ route('laboratory.results.verify', $result) }}" class="rounded-md bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Release Results</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-500">{{ $order->clinical_notes }}</p>
        @endif
    </x-card>
</div>
