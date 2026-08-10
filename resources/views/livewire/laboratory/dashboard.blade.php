<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach($cards as $label => $value)
            <x-card><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></x-card>
        @endforeach
    </div>

    <x-card>
        <div class="flex flex-col gap-4">
            <div>
                <h3 class="font-semibold">Verification and Release Worklist</h3>
                <p class="text-sm text-slate-500">Only the latest result version is shown. Pending work remains here until it is resolved.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach(['pending_verification' => 'Awaiting Verification', 'verified' => 'Awaiting Release', 'released' => 'Released / Completed'] as $key => $label)
                    <button type="button" wire:click="$set('worklistTab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $worklistTab === $key ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <x-text-input wire:model.live.debounce.300ms="search" placeholder="Patient, visit, order or test" />
                <x-select-input wire:model.live="dateRange">
                    <option value="all">All dates</option><option value="today">Today</option><option value="7_days">Last 7 days</option><option value="30_days">Last 30 days</option><option value="custom">Custom</option>
                </x-select-input>
                <x-select-input wire:model.live="testFilter"><option value="">All tests</option>@foreach($tests as $test)<option value="{{ $test->id }}">{{ $test->name }}</option>@endforeach</x-select-input>
                <x-select-input wire:model.live="sourceFilter"><option value="">All sources</option><option value="opd">OPD Order</option><option value="reception_direct">Direct Reception Laboratory</option></x-select-input>
                <x-select-input wire:model.live="priorityFilter"><option value="">All priorities</option><option value="stat">STAT</option><option value="urgent">Urgent</option><option value="routine">Routine</option></x-select-input>
                <x-select-input wire:model.live="flagFilter"><option value="">All result flags</option><option value="critical">Critical</option><option value="abnormal">Abnormal</option><option value="normal">Normal</option></x-select-input>
                @if($dateRange === 'custom')
                    <x-text-input type="date" wire:model.live="dateFrom" aria-label="From date" />
                    <x-text-input type="date" wire:model.live="dateTo" aria-label="To date" />
                @endif
            </div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Patient</th><th>Order / Test</th><th>Source</th><th>Priority</th><th>Status</th><th>Ordered / Submitted</th>@if($worklistTab === 'released')<th>Verified / Released</th><th>Report</th>@endif<th class="text-right">Action</th></tr></thead>
                <tbody>
                    @forelse($results as $result)
                        @php($order = $result->order)
                        <tr wire:key="laboratory-worklist-{{ $result->id }}" class="border-t border-slate-100 align-top dark:border-slate-800">
                            <td class="py-3"><span class="font-medium">{{ $order?->patient?->fullName() }}</span><div class="text-xs text-slate-500">{{ $order?->patient?->patient_number }} · {{ $order?->visit?->visit_number }}</div></td>
                            <td><span class="font-medium">{{ $order?->order_number }}</span><div class="text-xs text-slate-500">{{ $result->test?->name }}</div></td>
                            <td>{{ $order?->isDirectLaboratory() ? 'Direct Reception Laboratory' : 'OPD Order' }}</td>
                            <td>{{ str($order?->priority)->upper() }} @if($result->abnormal_flag?->value === 'critical')<span class="ml-1 font-semibold text-red-600">Critical</span>@endif</td>
                            <td>{{ match($result->result_status->value) {'pending_verification' => 'Awaiting Verification', 'verified' => 'Awaiting Release', default => 'Released / Completed'} }}</td>
                            <td><div>{{ $order?->ordered_at?->format('d M Y H:i') ?? '-' }}</div><div class="text-xs text-slate-500">Submitted {{ $result->entered_at?->diffForHumans() ?? '-' }}</div></td>
                            @if($worklistTab === 'released')
                                <td><div>{{ $result->verifier?->name ?? '-' }}</div><div class="text-xs text-slate-500">{{ $result->releaser?->name ?? '-' }} · {{ $result->released_at?->format('d M Y H:i') }}</div></td>
                                <td>{{ $order?->report_number ?? '-' }}</td>
                            @endif
                            <td class="text-right whitespace-nowrap">
                                @if($result->result_status->value === 'pending_verification')
                                    @can('verify', $result)<a href="{{ route('laboratory.results.verify', $result) }}" class="font-semibold text-primary">Verify</a>@endcan
                                @elseif($result->result_status->value === 'verified')
                                    @can('release', $result)<a href="{{ route('laboratory.results.verify', $result) }}" class="font-semibold text-amber-700">Release Results</a>@endcan
                                @elseif($reportEligibility[$order?->id] ?? false)
                                    <div class="flex justify-end gap-2">
                                        @can('viewReport', $order)<a href="{{ route('laboratory.orders.report.view', $order) }}" target="_blank" class="text-primary">View Result / Angalia Majibu</a>@endcan
                                        @can('downloadReport', $order)<a href="{{ route('laboratory.orders.report.download', $order) }}" class="text-primary">Download / Pakua Majibu</a>@endcan
                                        @can('printReport', $order)<a href="{{ route('laboratory.orders.report.print', $order) }}" target="_blank" class="text-primary">Print / Chapisha Majibu</a>@endcan
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 text-center text-slate-500">No results match this worklist.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $results->links() }}</div>
    </x-card>

    <x-card>
        <h3 class="mb-3 font-semibold">Urgent Orders</h3>
        <div class="grid gap-2 md:grid-cols-2">
            @forelse($urgent as $order)<a href="{{ route('laboratory.orders.show', $order) }}" class="block rounded-md border border-slate-200 p-3 dark:border-slate-700">{{ $order->order_number }} · {{ $order->patient?->fullName() }}</a>@empty<p class="text-sm text-slate-500">No urgent orders.</p>@endforelse
        </div>
    </x-card>
</div>
