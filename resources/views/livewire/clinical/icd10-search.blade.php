<div class="space-y-2">
    <x-text-input wire:model.live.debounce.300ms="query" placeholder="Tafuta ICD-10 code au title..." />
    @if(strlen($query) >= 2)
        <div class="max-h-64 overflow-y-auto rounded-md border border-slate-200 dark:border-slate-700">
            @forelse($results as $code)
                <button type="button" wire:click="selectCode('{{ $code->code }}','{{ addslashes($code->title) }}')" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><span class="font-semibold">{{ $code->code }}</span> {{ $code->title }}</button>
            @empty
                <p class="px-3 py-2 text-sm text-slate-500">Hakuna matokeo.</p>
            @endforelse
        </div>
    @endif
</div>
