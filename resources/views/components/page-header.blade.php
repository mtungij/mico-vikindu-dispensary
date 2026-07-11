@props(['title', 'description' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <x-breadcrumb :items="[['label' => 'Dashibodi', 'url' => route('dashboard')], ['label' => $title]]" />
        <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
