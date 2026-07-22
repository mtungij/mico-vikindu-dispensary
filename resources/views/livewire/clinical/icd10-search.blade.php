<div class="relative space-y-2">
    <x-text-input
        wire:model.live.debounce.300ms="query"
        placeholder="Search ICD-10 code or diagnosis..."
        autocomplete="off"
        aria-label="Search ICD-10 code or diagnosis"
    />

    @if(! $catalogueAvailable)
        <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            No ICD-10 codes are available. Ask the administrator to import the ICD-10 catalogue.
        </p>
    @elseif($developmentSamplesOnly && auth()->user()?->can('icd10.manage'))
        <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            The ICD-10 catalogue contains development sample records only. Import an approved full catalogue before production use.
        </p>
    @elseif($showResults && mb_strlen(trim($query)) >= 2)
        <div class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-card-dark">
            @forelse($results as $code)
                <button type="button" wire:click="selectCode({{ $code->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="font-semibold">{{ $code->code }}</span> — {{ $code->title }}
                </button>
            @empty
                <p class="px-3 py-2 text-sm text-slate-500">No matching ICD-10 codes found.</p>
            @endforelse
        </div>
    @endif
</div>
