<div class="space-y-6">
    <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 md:grid-cols-4">
        <input type="date" wire:model.live="from" class="rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
        <input type="date" wire:model.live="to" class="rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
        <select wire:model.live="departmentId" class="rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
            <option value="">All departments</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach($summary as $label => $value)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ str($label)->replace('_', ' ') }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Department Workload</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($workload as $row)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="text-slate-700 dark:text-slate-200">{{ $row->department?->name ?? 'Department '.$row->department_id }}</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $row->total }}</span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-sm text-slate-500">No queue data.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Recent Movements</h2>
            </div>
            <div class="max-h-[28rem] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($movements as $movement)
                    <div class="px-4 py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $movement->fromDepartment?->name ?? 'Start' }} -> {{ $movement->toDepartment?->name ?? 'End' }}</span>
                            <span class="text-xs text-slate-500">{{ $movement->moved_at?->format('H:i') }}</span>
                        </div>
                        <p class="mt-1 text-slate-500 dark:text-slate-400">{{ $movement->reason ?? $movement->movement_type }}</p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-sm text-slate-500">No movements.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
