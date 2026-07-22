<div class="space-y-5">
    @if($developmentSamplesOnly)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            The ICD-10 catalogue contains development sample records only. Import an approved full catalogue before production use.
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <x-card><p class="text-sm text-slate-500">Total codes</p><p class="mt-1 text-2xl font-semibold">{{ number_format($total) }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Active codes</p><p class="mt-1 text-2xl font-semibold text-emerald-600">{{ number_format($active) }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Inactive codes</p><p class="mt-1 text-2xl font-semibold text-slate-500">{{ number_format($inactive) }}</p></x-card>
    </div>

    <x-card>
        <h2 class="font-semibold">Last catalogue import</h2>
        @if($lastImport)
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                {{ $lastImport->new_values['source'] ?? 'Unknown source' }}
                @if(filled($lastImport->new_values['source_version'] ?? null)) · Version {{ $lastImport->new_values['source_version'] }} @endif
                · {{ $lastImport->created_at?->format('d/m/Y H:i') }}
                @if($lastImport->new_values['dry_run'] ?? false) · Dry run @endif
            </p>
        @else
            <p class="mt-2 text-sm text-slate-500">No catalogue import has been recorded.</p>
        @endif
    </x-card>

    @can('icd10.import')
        <x-card>
            <h2 class="font-semibold">Upload approved catalogue</h2>
            <p class="mt-1 text-sm text-slate-500">CSV only, maximum 50 MB. Run a dry-run first and review its summary before importing.</p>
            <form wire:submit="import" class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="md:col-span-2"><span class="text-sm">Approved CSV file</span><input type="file" wire:model="csvFile" accept=".csv,text/csv" class="mt-1 block w-full text-sm" /></label>
                <x-text-input wire:model="source" placeholder="Source, e.g. Approved Ministry Catalogue" />
                <x-text-input wire:model="sourceVersion" placeholder="Source version, e.g. 2026" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="dryRun" /> Dry-run only (no catalogue changes)</label>
                <div class="md:text-right"><x-primary-button wire:loading.attr="disabled">Validate / Import</x-primary-button></div>
                <x-input-error :messages="$errors->get('csvFile')" class="md:col-span-2" />
                <x-input-error :messages="$errors->get('source')" class="md:col-span-2" />
            </form>

            @if($importResult)
                <div class="mt-4 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                    <p class="font-semibold">{{ $importResult['dry_run'] ? 'Dry-run summary' : 'Import summary' }}</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach(['inserted', 'updated', 'unchanged', 'skipped', 'failed', 'total'] as $metric)
                            <p><span class="block text-xs text-slate-500">{{ str($metric)->headline() }}</span>{{ number_format($importResult[$metric]) }}</p>
                        @endforeach
                    </div>
                    @foreach($importResult['failures'] as $failure)
                        <p class="mt-1 text-amber-700 dark:text-amber-300">Row {{ $failure['row'] }}: {{ $failure['reason'] }}</p>
                    @endforeach
                </div>
            @endif
        </x-card>
    @else
        <x-card><p class="text-sm">Imports are performed with <code>php artisan icd10:import /path/to/catalogue.csv --dry-run</code>. Ask an administrator with <code>icd10.import</code> permission.</p></x-card>
    @endcan

    <x-card>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-semibold">Catalogue records</h2>
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search code or diagnosis..." class="max-w-md" />
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Code</th><th>Title</th><th>Chapter</th><th>Category</th><th>Billable</th><th>Active</th></tr></thead>
                <tbody>
                    @forelse($codes as $code)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $code->code }}</td><td>{{ $code->title }}</td><td>{{ $code->chapter ?? '—' }}</td><td>{{ $code->category ?? '—' }}</td>
                            <td>@can('icd10.manage')<button type="button" wire:click="toggleBillable({{ $code->id }})" class="rounded-full px-2 py-1 text-xs {{ $code->is_billable ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $code->is_billable ? 'Yes' : 'No' }}</button>@else{{ $code->is_billable ? 'Yes' : 'No' }}@endcan</td>
                            <td>@can('icd10.manage')<button type="button" wire:click="toggleActive({{ $code->id }})" class="rounded-full px-2 py-1 text-xs {{ $code->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $code->is_active ? 'Active' : 'Inactive' }}</button>@else{{ $code->is_active ? 'Active' : 'Inactive' }}@endcan</td>
                        </tr>
                    @empty<tr><td colspan="6" class="py-6 text-center text-slate-500">No ICD-10 codes found.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $codes->links() }}</div>
    </x-card>
</div>
