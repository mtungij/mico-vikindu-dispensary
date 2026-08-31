@php
    $compact = $compact ?? false;
    $alerts = $triage?->clinicalAlerts ?? collect();
    $level = $triage?->triage_level?->value;
    $levelTone = match ($level) {
        'emergency' => 'border-red-300 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200',
        'urgent' => 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-800 dark:bg-orange-950/50 dark:text-orange-200',
        'priority' => 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
        default => 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
    };
    $alertTone = fn (string $severity) => match ($severity) {
        'critical' => 'border-red-300 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200',
        'high' => 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-800 dark:bg-orange-950/50 dark:text-orange-200',
        default => 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    };
    $formatNumber = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim((string) $value, '0'), '.');
    };
    $alertTitles = [
        'blood_pressure' => ['Severe hypertension', 'Hypotension'],
        'temperature' => ['High fever', 'Fever', 'Hypothermia'],
        'pulse_rate' => ['Tachycardia', 'Bradycardia'],
        'oxygen_saturation' => ['Low oxygen saturation'],
        'blood_glucose' => ['Hypoglycemia', 'Hyperglycemia'],
        'pain_score' => ['Severe pain'],
    ];
    $vitals = collect([
        ['key' => 'blood_pressure', 'label' => 'Blood pressure', 'value' => $triage && $triage->systolic_bp !== null && $triage->diastolic_bp !== null ? $triage->systolic_bp.'/'.$triage->diastolic_bp : null, 'unit' => 'mmHg'],
        ['key' => 'temperature', 'label' => 'Temperature', 'value' => $triage ? $formatNumber($triage->temperature) : null, 'unit' => '°C'],
        ['key' => 'pulse_rate', 'label' => 'Pulse', 'value' => $triage?->pulse_rate, 'unit' => 'bpm'],
        ['key' => 'respiratory_rate', 'label' => 'Respiratory rate', 'value' => $triage?->respiratory_rate, 'unit' => '/min'],
        ['key' => 'oxygen_saturation', 'label' => 'SpO₂', 'value' => $triage ? $formatNumber($triage->oxygen_saturation) : null, 'unit' => '%'],
        ['key' => 'weight_kg', 'label' => 'Weight', 'value' => $triage ? $formatNumber($triage->weight_kg) : null, 'unit' => 'kg'],
        ['key' => 'height_cm', 'label' => 'Height', 'value' => $triage ? $formatNumber($triage->height_cm) : null, 'unit' => 'cm'],
        ['key' => 'bmi', 'label' => 'BMI', 'value' => $triage ? $formatNumber($triage->bmi) : null, 'unit' => ''],
        ['key' => 'blood_glucose', 'label' => 'Blood glucose', 'value' => $triage ? $formatNumber($triage->blood_glucose) : null, 'unit' => 'mmol/L'],
        ['key' => 'muac_cm', 'label' => 'MUAC', 'value' => $triage ? $formatNumber($triage->muac_cm) : null, 'unit' => 'cm'],
        ['key' => 'pain_score', 'label' => 'Pain score', 'value' => $triage?->pain_score, 'unit' => '/10'],
    ])->filter(fn (array $vital) => $vital['value'] !== null && $vital['value'] !== '');
@endphp

@if($compact)
    @if($triage)
        <div class="min-w-64 space-y-2 py-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $levelTone }}">{{ $triage->triage_level?->label() ?? str($level)->title() }}</span>
                <span class="text-[11px] text-slate-500">{{ ($triage->completed_at ?? $triage->assessed_at)?->format('d M H:i') }}</span>
            </div>
            <p class="font-medium text-slate-800 dark:text-slate-100">{{ filled($triage->chief_complaint_summary) ? str($triage->chief_complaint_summary)->limit(85) : 'Chief complaint not recorded.' }}</p>
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                @foreach($vitals->whereIn('key', ['temperature', 'blood_pressure', 'oxygen_saturation']) as $vital)
                    <span><span class="font-medium">{{ $vital['label'] }}:</span> {{ $vital['value'] }} {{ $vital['unit'] }}</span>
                @endforeach
            </div>
            @if($alerts->isNotEmpty())
                <div class="flex flex-wrap gap-1">
                    @foreach($alerts->take(2) as $alert)
                        <span class="rounded-full border px-1.5 py-0.5 text-[10px] font-semibold {{ $alertTone($alert->severity) }}">{{ $alert->title }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <p class="min-w-52 py-2 text-xs text-slate-500">No triage assessment recorded for this visit.</p>
    @endif
@else
    <section aria-labelledby="triage-summary-title" class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-card-dark">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="rounded-lg bg-primary/10 p-2 text-primary"><x-lucide-heart-pulse class="h-5 w-5" /></span>
                <div>
                    <h2 id="triage-summary-title" class="font-semibold tracking-wide">TRIAGE SUMMARY</h2>
                    @if($triage && $triage->sequence_number > 1)<p class="text-xs text-slate-500">Updated triage · assessment {{ $triage->sequence_number }}</p>@endif
                </div>
            </div>
            @if($triage)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $levelTone }}">{{ $triage->triage_level?->label() ?? str($level)->title() }}</span>
                    <span class="text-xs text-slate-500">{{ ($triage->completed_at ?? $triage->assessed_at)?->format('d M Y H:i') }}</span>
                </div>
            @endif
        </div>

        @if($triage)
            <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Chief complaint</p>
                        <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ filled($triage->chief_complaint_summary) ? $triage->chief_complaint_summary : 'Chief complaint not recorded.' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Allergies</p>
                        @if(filled($patient->known_allergies))
                            <p class="mt-1 rounded-md border border-red-200 bg-red-50 px-3 py-2 font-semibold text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $patient->known_allergies }}</p>
                        @else
                            <p class="mt-1 text-sm text-slate-500">No allergy details recorded.</p>
                        @endif
                    </div>
                    @if(filled($triage->notes))
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Triage notes</p>
                            <p class="mt-1 whitespace-pre-line break-words text-sm">{{ $triage->notes }}</p>
                        </div>
                    @endif
                    @if(! empty($triage->danger_signs))
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-600 dark:text-red-300">Danger signs</p>
                            <p class="mt-1 text-sm font-medium">{{ collect($triage->danger_signs)->join(', ') }}</p>
                        </div>
                    @endif
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Triaged</dt><dd class="font-medium">{{ ($triage->completed_at ?? $triage->assessed_at)?->format('d M Y H:i') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">By</dt><dd class="font-medium">{{ $triage->completedBy?->name ?? $triage->assessor?->name ?? 'Not recorded' }}</dd></div>
                    </dl>
                </div>

                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Vital signs</p>
                    @if($vitals->isNotEmpty())
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($vitals as $vital)
                                @php($vitalAlerts = $alerts->whereIn('title', $alertTitles[$vital['key']] ?? []))
                                <div @class(['rounded-md border p-3', 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40' => $vitalAlerts->isEmpty(), 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/20' => $vitalAlerts->isNotEmpty()])>
                                    <p class="text-xs text-slate-500">{{ $vital['label'] }}</p>
                                    <p class="mt-1 text-lg font-semibold">{{ $vital['value'] }} <span class="text-xs font-normal text-slate-500">{{ $vital['unit'] }}</span></p>
                                    @foreach($vitalAlerts as $alert)
                                        <span class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $alertTone($alert->severity) }}">{{ $alert->title }}</span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No vital signs recorded in this triage assessment.</p>
                    @endif
                </div>
            </div>
        @else
            <div class="px-5 py-6 text-sm text-slate-500">No triage assessment recorded for this visit.</div>
        @endif
    </section>
@endif
