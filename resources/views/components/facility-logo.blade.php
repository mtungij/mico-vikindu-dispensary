@props(['facility' => null, 'class' => 'h-10 w-10'])
@if ($facility?->logo_path)
    <img src="{{ asset('storage/'.$facility->logo_path) }}" alt="{{ $facility->name }}" {{ $attributes->merge(['class' => $class.' rounded-lg object-contain bg-white']) }}>
@else
    <div {{ $attributes->merge(['class' => $class.' flex items-center justify-center rounded-lg bg-primary text-white']) }}>
        <x-lucide-building-2 class="h-5 w-5" />
    </div>
@endif
