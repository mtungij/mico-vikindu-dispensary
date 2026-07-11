@props(['show' => false, 'title' => 'Thibitisha hatua', 'message' => 'Una uhakika unataka kuendelea?', 'confirm' => null, 'cancel' => null, 'confirmText' => 'Thibitisha', 'cancelText' => 'Ghairi', 'tone' => 'danger'])
<x-modal :show="$show" :title="$title" max-width="md" :close="$cancel">
    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $message }}</p>
    <x-slot:footer>
        @if ($cancel)<x-secondary-button wire:click="{{ $cancel }}">{{ $cancelText }}</x-secondary-button>@endif
        @if ($confirm)<x-danger-button wire:click="{{ $confirm }}" wire:loading.attr="disabled"><x-lucide-trash-2 class="h-4 w-4" /> {{ $confirmText }}</x-danger-button>@endif
    </x-slot:footer>
</x-modal>
