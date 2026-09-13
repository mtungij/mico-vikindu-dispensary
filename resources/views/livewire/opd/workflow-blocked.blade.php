<div class="mx-auto max-w-3xl space-y-6 py-4 sm:py-8">
    <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <span>OPD</span><span aria-hidden="true">/</span><span>{{ $visit->visit_number }}</span><span aria-hidden="true">/</span><span>Hali ya huduma</span>
    </nav>
    <section aria-labelledby="workflow-title" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-card-dark">
        <div class="border-b border-teal-100 bg-teal-50 p-6 sm:p-8 dark:border-teal-900 dark:bg-teal-950/40">
            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200">
                <x-lucide-info class="h-6 w-6" aria-hidden="true" />
            </div>
            <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ $state['title'] }}</p>
            <h1 id="workflow-title" class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $state['heading'] }}</h1>
        </div>
        <div class="space-y-6 p-6 sm:p-8">
            <dl class="grid gap-5 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500 dark:text-slate-400">Mgonjwa</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $visit->patient->fullName() }}</dd><dd class="text-slate-500 dark:text-slate-400">{{ $visit->patient->patient_number }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Visit</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $visit->visit_number }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Hali ya sasa</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $state['status_label'] }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Huduma inayofuata</dt><dd class="mt-1 font-semibold text-teal-700 dark:text-teal-300">{{ $state['next_destination'] }}</dd></div>
            </dl>
            <div class="space-y-2 text-slate-700 dark:text-slate-300">
                @if($state['completion_message'])<p>{{ $state['completion_message'] }}</p>@endif
                <p>{{ $state['message'] }}</p>
            </div>
            @if($state['balance'])
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                    <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('opd_workflow.balance_message', ['balance' => $state['balance']]) }}</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-950 dark:text-amber-100">{{ $state['balance'] }}</p>
                </div>
            @endif
            <div class="rounded-lg bg-slate-50 p-5 dark:bg-slate-800/60">
                <h2 class="font-semibold text-slate-900 dark:text-white">Hatua inayofuata</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $state['next_action'] }}</p>
                @if($state['after_payment'])<p class="mt-2 text-sm font-medium text-teal-700 dark:text-teal-300">Baada ya malipo: {{ $state['after_payment'] }}</p>@endif
            </div>
            @if(count($actions))
                <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-6 dark:border-slate-700">
                    @foreach($actions as $action)
                        <a href="{{ $action['url'] }}" class="inline-flex items-center justify-center rounded-lg border border-teal-600 px-4 py-2.5 text-sm font-semibold text-teal-700 transition hover:bg-teal-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:border-teal-400 dark:text-teal-300 dark:hover:bg-teal-950">{{ $action['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
