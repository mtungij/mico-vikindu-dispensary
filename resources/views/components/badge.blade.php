@props(['tone' => 'info'])
@php($classes = [
    'success' => 'bg-green-50 text-success ring-green-600/20 dark:bg-green-950/30',
    'warning' => 'bg-amber-50 text-warning ring-amber-600/20 dark:bg-amber-950/30',
    'danger' => 'bg-red-50 text-danger ring-red-600/20 dark:bg-red-950/30',
    'info' => 'bg-blue-50 text-info ring-blue-600/20 dark:bg-blue-950/30',
][$tone] ?? 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-200')
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset '.$classes]) }}>{{ $slot }}</span>
