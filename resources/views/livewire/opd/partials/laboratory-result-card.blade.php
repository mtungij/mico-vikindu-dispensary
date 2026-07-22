@php
    $result = $canViewLaboratoryResults && $item->relationLoaded('results')
        ? $item->results->sortByDesc('result_version')->first()
        : null;
    $resultStatus = $result?->result_status?->value ?? $item->result_status;
    $displayStatus = $resultStatus ?: ($order->status?->value ?? (string) $order->status);
    if ($displayStatus === 'result_ready' && ! $result) {
        $displayStatus = 'processing';
    }
    $statusLabels = [
        'awaiting_payment' => 'Awaiting Payment',
        'ordered' => 'Ordered',
        'sample_pending' => 'In Processing',
        'sample_collected' => 'In Processing',
        'sample_accepted' => 'In Processing',
        'processing' => 'In Processing',
        'draft' => 'In Processing',
        'entered' => 'In Processing',
        'pending_verification' => 'Awaiting Verification',
        'verified' => 'Verified',
        'released' => 'Released',
        'completed' => 'Completed',
    ];
    $statusClasses = [
        'pending_verification' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        'verified' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
        'released' => 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-200',
        'processing' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
    ];
    $mayExposeValues = $canViewLaboratoryResults
        && $result
        && $result->facility_id === currentFacility()?->id
        && in_array($resultStatus, ['verified', 'released'], true);
@endphp

<div wire:key="opd-laboratory-result-{{ $item->id }}" class="rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="font-medium">{{ $item->test_name_snapshot }}</p>
            <p class="text-xs text-slate-500">Order: {{ $order->order_number }} · {{ $order->ordered_at?->format('d/m/Y H:i') }}</p>
        </div>
        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $statusClasses[$displayStatus] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }}">{{ $statusLabels[$displayStatus] ?? str($displayStatus)->replace('_', ' ')->title() }}</span>
    </div>

    @if($resultStatus === 'pending_verification')
        <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">Matokeo yanasubiri uthibitisho. Thamani hazijaonyeshwa bado.</p>
    @elseif($mayExposeValues)
        <details class="mt-3" open>
            <summary class="cursor-pointer font-semibold text-primary">View Result</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead><tr class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700"><th class="py-2 pr-3">Parameter</th><th class="py-2 pr-3">Result</th><th class="py-2 pr-3">Unit</th><th class="py-2 pr-3">Reference Range</th><th class="py-2">Flag</th></tr></thead>
                    <tbody>
                        @foreach($result->values as $value)
                            @php($flag = $value->abnormal_flag?->value ?? '')
                            <tr class="border-b border-slate-100 last:border-0 dark:border-slate-800">
                                <td class="py-2 pr-3 font-medium">{{ $value->parameter_name_snapshot }}</td>
                                <td class="py-2 pr-3">{{ $value->displayValue() }}</td>
                                <td class="py-2 pr-3">{{ $value->unit_snapshot ?: '-' }}</td>
                                <td class="py-2 pr-3">{{ $value->reference_range_snapshot ?: '-' }}</td>
                                <td class="py-2"><span class="rounded px-2 py-1 text-xs font-semibold {{ $value->is_critical ? 'bg-red-100 text-red-800' : ($flag && $flag !== 'normal' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">{{ $flag ? str($flag)->replace('_', ' ')->title() : 'Normal' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($result->overall_result || $result->interpretation || $result->comments)
                <dl class="mt-3 grid gap-2 text-xs md:grid-cols-2">
                    @if($result->overall_result)<div><dt class="text-slate-500">Overall result</dt><dd>{{ $result->overall_result }}</dd></div>@endif
                    @if($result->interpretation)<div><dt class="text-slate-500">Interpretation</dt><dd>{{ $result->interpretation }}</dd></div>@endif
                    @if($result->comments)<div><dt class="text-slate-500">Laboratory remarks</dt><dd>{{ $result->comments }}</dd></div>@endif
                </dl>
            @endif

            <dl class="mt-3 grid gap-2 border-t border-slate-200 pt-3 text-xs dark:border-slate-700 md:grid-cols-2">
                <div><dt class="text-slate-500">Verified by</dt><dd>{{ $result->verifier?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">Verified at</dt><dd>{{ $result->verified_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                @if($resultStatus === 'released')
                    <div><dt class="text-slate-500">Released by</dt><dd>{{ $result->releaser?->name ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">Released at</dt><dd>{{ $result->released_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                @endif
            </dl>
        </details>
    @elseif(!$canViewLaboratoryResults && in_array($resultStatus, ['verified', 'released'], true))
        <p class="mt-3 text-xs text-slate-500">Huna ruhusa ya kuona matokeo ya maabara.</p>
    @endif
</div>
