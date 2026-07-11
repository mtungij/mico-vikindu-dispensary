<x-layouts.app title="Wasifu wangu" description="Taarifa za akaunti yako na hali ya mtumiaji.">
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card>
            <div class="flex flex-col items-center text-center">
                <div class="flex h-24 w-24 items-center justify-center rounded-lg bg-primary text-3xl font-semibold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <h2 class="mt-4 font-semibold">{{ auth()->user()->name }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                <x-badge class="mt-3" :tone="auth()->user()->status->badge()">{{ auth()->user()->status->label() }}</x-badge>
            </div>
        </x-card>
        <x-card class="lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Taarifa za akaunti</h2>
                <livewire:profile.edit-profile />
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Jina</dt><dd class="font-medium">{{ auth()->user()->name }}</dd></div>
                <div><dt class="text-sm text-slate-500">Barua pepe</dt><dd class="font-medium">{{ auth()->user()->email }}</dd></div>
                <div><dt class="text-sm text-slate-500">Simu</dt><dd class="font-medium">{{ auth()->user()->phone ?? 'Haijawekwa' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Last login</dt><dd class="font-medium">{{ auth()->user()->last_login_at?->format('d/m/Y H:i') ?? 'Hakuna' }}</dd></div>
            </dl>
        </x-card>
    </div>
</x-layouts.app>
