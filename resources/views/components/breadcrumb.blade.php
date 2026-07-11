@props(['items' => []])
<nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
    @foreach ($items as $item)
        @if (! $loop->first)<span>/</span>@endif
        @if (isset($item['url']) && ! $loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-primary">{{ $item['label'] }}</a>
        @else
            <span class="text-slate-700 dark:text-slate-200">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
