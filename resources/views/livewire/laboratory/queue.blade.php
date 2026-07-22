<div class="space-y-6" wire:poll.30s.visible>
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-2">
            @foreach(['awaiting_payment' => 'Awaiting Payment', 'awaiting_sample' => 'Awaiting Sample', 'processing' => 'Processing', 'completed' => 'Completed'] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm {{ $tab === $key ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800' }}">{{ $label }}</button>
            @endforeach
        </div>
        <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta..." />
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Order</th><th>Patient</th><th>Tests</th><th>Priority</th><th>Payment</th><th>Status</th><th>Ordered</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr wire:key="laboratory-order-{{ $order->id }}" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $order->order_number }}</td>
                            <td>{{ $order->patient?->fullName() }}<div class="text-xs text-slate-500">{{ $order->patient?->patient_number }}</div></td>
                            <td>{{ $order->items->pluck('test_name_snapshot')->implode(', ') }}</td>
                            <td>{{ $order->priority }}</td>
                            <td>{{ $order->payment_status->value }}</td>
                            <td>{{ $order->status->value }}</td>
                            <td>{{ $order->ordered_at?->diffForHumans() }}</td>
                            <td class="text-right">
                                <a href="{{ route('laboratory.orders.show', $order) }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-eye class="h-4 w-4" /></a>
                                @if(auth()->user()->can('laboratory.collect-sample') && auth()->user()->can('laboratory.accept-sample') && in_array($order->status->value, ['ordered', 'awaiting_payment'], true) && $order->items->every(fn ($item) => $item->sample_id === null))
                                    @if($order->payment_status->value !== 'pending' || auth()->user()->can('laboratory.override-payment'))
                                        <button type="button" wire:click="openCollect({{ $order->id }})" wire:loading.attr="disabled" wire:target="openCollect({{ $order->id }})" class="rounded-md p-2 hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-slate-800" aria-label="Collect and accept sample"><x-lucide-test-tube class="h-4 w-4" /></button>
                                    @endif
                                @endif
                                @if(auth()->user()->can('laboratory-results.enter') && $order->items->contains(fn ($item) => $item->sample?->sample_status?->value === 'accepted'))
                                    <a href="{{ route('laboratory.results.entry', $order) }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-clipboard-check class="h-4 w-4" /></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </x-card>

    <x-modal :show="$showCollectModal" title="Collect Sample" close="$set('showCollectModal', false)" maxWidth="2xl">
        <form wire:submit.prevent="collectAndAccept" class="space-y-3" wire:key="collect-sample-{{ $selectedOrder?->id ?? 'none' }}">
            @if($errors->any())
                <div id="collection-errors" role="alert" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p class="font-semibold">Sampuli haijakusanywa:</p>
                    <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            <p class="text-sm text-slate-500">Sample number itatengenezwa server-side.</p>
            <div><x-text-input type="datetime-local" wire:model="sampleForm.collected_at" :class="$errors->has('sampleForm.collected_at') ? 'border-red-500' : ''" /><x-input-error :messages="$errors->get('sampleForm.collected_at')" /></div>
            <div><x-text-input wire:model="sampleForm.volume_collected" placeholder="Volume" :class="$errors->has('sampleForm.volume_collected') ? 'border-red-500' : ''" /><x-input-error :messages="$errors->get('sampleForm.volume_collected')" /></div>
            <x-text-input wire:model="sampleForm.volume_unit" placeholder="Unit" />
            <x-text-input wire:model="sampleForm.collection_location" placeholder="Location" />
            <x-textarea wire:model="sampleForm.collection_notes" rows="3" placeholder="Notes" />
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="collectAndAccept" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                    <span wire:loading.remove wire:target="collectAndAccept">Collect and Accept</span>
                    <span wire:loading wire:target="collectAndAccept">Inachakata...</span>
                </button>
            </div>
        </form>
    </x-modal>

    @script
        <script>
            $wire.on('laboratory-validation-failed', () => requestAnimationFrame(() => document.getElementById('collection-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' })))
        </script>
    @endscript
</div>
