<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700']) }}>
    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">{{ $slot }}</table>
</div>
