<div class="space-y-6">
    @php($facility = app(\App\Services\FacilityContext::class)->current())
    <x-card class="bg-primary text-white">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-teal-100">Karibu</p>
                <h2 class="mt-1 text-xl font-semibold">{{ auth()->user()->name }}</h2>
                <p class="mt-2 max-w-2xl text-sm text-teal-50">{{ $facility?->name ?? 'Professional Dispensary Management System' }} iko tayari kwa modules zitakazofuata.</p>
            </div>
            <x-badge tone="info" class="bg-white/15 text-white ring-white/30">Step 6</x-badge>
        </div>
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->stats() as $stat)
            @php($statUrl = $stat['url'] ?? null)
            @if($statUrl)
                <a wire:key="stat-{{ $stat['label'] }}" href="{{ $statUrl }}" class="block rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                    <x-card class="h-full transition hover:border-primary/40 hover:shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $stat['value'] }}</p>
                            </div>
                            <div class="rounded-md bg-slate-100 p-3 text-primary dark:bg-slate-800">
                                <x-dynamic-component :component="'lucide-'.$stat['icon']" class="h-6 w-6" />
                            </div>
                        </div>
                        <div class="mt-4 h-2 rounded bg-slate-100 dark:bg-slate-800"><div class="h-2 w-0 rounded bg-primary"></div></div>
                    </x-card>
                </a>
            @else
                <x-card wire:key="stat-{{ $stat['label'] }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold">{{ $stat['value'] }}</p>
                        </div>
                        <div class="rounded-md bg-slate-100 p-3 text-primary dark:bg-slate-800">
                            <x-dynamic-component :component="'lucide-'.$stat['icon']" class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4 h-2 rounded bg-slate-100 dark:bg-slate-800"><div class="h-2 w-0 rounded bg-primary"></div></div>
                </x-card>
            @endif
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2">
            <h3 class="font-semibold">Quick Actions</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($this->quickActions() as $action)
                    <a href="{{ route($action['route']) }}" class="flex items-center gap-3 rounded-md border border-slate-200 p-4 text-left hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <span class="rounded-md bg-slate-100 p-2 text-primary dark:bg-slate-900"><x-dynamic-component :component="'lucide-'.$action['icon']" class="h-5 w-5" /></span>
                        <span>
                            <span class="block text-sm font-medium">{{ $action['label'] }}</span>
                            <span class="text-xs text-slate-500">Settings foundation</span>
                        </span>
                    </a>
                @empty
                    <x-empty-state icon="shield-alert" title="Hakuna quick actions" message="Quick actions zitaonekana kulingana na permissions zako." />
                @endforelse
            </div>
        </x-card>
        <x-card>
            <h3 class="font-semibold">System Setup Progress</h3>
            <div class="mt-4 space-y-3">
                @foreach ($this->setupProgress() as $item)
                    <a href="{{ $item['route'] ? route($item['route']) : '#' }}" class="flex items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span class="flex items-center gap-2">
                            @if($item['completed'])
                                <x-lucide-shield-check class="h-4 w-4 text-success" />
                            @else
                                <x-lucide-circle-dashed class="h-4 w-4 text-warning" />
                            @endif
                            {{ $item['label'] }}
                        </span>
                        <x-badge :tone="$item['completed'] ? 'success' : 'warning'">{{ $item['completed'] ? 'Done' : 'Pending' }}</x-badge>
                    </a>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="font-semibold">Recent Activity</h3>
            <x-empty-state class="mt-4" icon="bell" title="Hakuna activity bado" message="Matukio ya mfumo yataonekana hapa." />
        </x-card>
        <x-card>
            <h3 class="font-semibold">Department Status</h3>
            <div class="mt-4 space-y-3">
                @forelse ($this->departmentStatuses() as $department)
                    <div class="flex items-center justify-between rounded-md bg-slate-50 p-3 dark:bg-slate-800">
                        <span class="text-sm">{{ $department->name }}</span>
                        <x-badge :tone="$department->is_active ? 'success' : 'danger'">{{ $department->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </div>
                @empty
                    <x-empty-state icon="network" title="Hakuna departments" message="Departments zitaonekana baada ya setup." />
                @endforelse
            </div>
        </x-card>
    </div>
</div>
