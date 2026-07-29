<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">@foreach($cards as $label => $value)<x-card><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></x-card>@endforeach</div>
    <div class="grid gap-6 xl:grid-cols-2"><x-card><h3 class="mb-3 font-semibold">Urgent Orders</h3><div class="space-y-2">@foreach($urgent as $order)<a href="{{ route('laboratory.orders.show', $order) }}" class="block rounded-md border border-slate-200 p-3 dark:border-slate-700">{{ $order->order_number }} · {{ $order->patient?->fullName() }}</a>@endforeach</div></x-card><x-card><h3 class="mb-3 font-semibold">Verification and Release Queue</h3><div class="space-y-2">@foreach($verification as $result)<a href="{{ route('laboratory.results.verify', $result) }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 dark:border-slate-700"><span>{{ $result->test?->name }} · {{ $result->order?->patient?->fullName() }}</span><span class="text-xs font-semibold {{ $result->result_status->value === 'verified' ? 'text-amber-700' : 'text-primary' }}">{{ $result->result_status->value === 'verified' ? 'Release Results' : 'Verify' }}</span></a>@endforeach</div></x-card></div>
    <x-card>
        <h3 class="mb-3 font-semibold">Released Results</h3>
        <div class="space-y-2">
            @forelse($releasedOrders as $order)
                <div class="flex flex-col gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700 md:flex-row md:items-center md:justify-between">
                    <div><p class="font-semibold">{{ $order->order_number }} · {{ $order->patient?->fullName() }}</p><p class="text-xs text-slate-500">{{ $order->isDirectLaboratory() ? 'Direct Laboratory' : 'OPD' }} · {{ $order->completed_at?->format('d/m/Y H:i') }}</p></div>
                    <div class="flex flex-wrap gap-2">
                        @can('viewReport', $order)<a href="{{ route('laboratory.orders.report.view', $order) }}" target="_blank" class="text-sm text-primary">Angalia Majibu</a>@endcan
                        @can('downloadReport', $order)<a href="{{ route('laboratory.orders.report.download', $order) }}" class="text-sm text-primary">Pakua Majibu</a>@endcan
                        @can('printReport', $order)<a href="{{ route('laboratory.orders.report.print', $order) }}" target="_blank" class="text-sm text-primary">Chapisha Majibu</a>@endcan
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Hakuna majibu yaliyotolewa.</p>
            @endforelse
        </div>
    </x-card>
</div>
