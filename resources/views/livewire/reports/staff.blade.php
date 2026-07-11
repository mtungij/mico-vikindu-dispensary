<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid gap-3 md:grid-cols-3 lg:min-w-[720px]">
            <x-select-input wire:model.live="department"><option value="">Departments zote</option>@foreach($departments as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select-input>
            <x-select-input wire:model.live="jobTitle"><option value="">Vyeo vyote</option>@foreach($jobTitles as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select-input>
            <x-select-input wire:model.live="role"><option value="">Roles zote</option>@foreach($roles as $item)<option value="{{ $item->id }}">{{ $item->display_name ?? $item->name }}</option>@endforeach</x-select-input>
        </div>
        <a href="{{ route('reports.staff.export') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800">
            <x-lucide-download class="h-4 w-4" /> CSV
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <x-card><p class="text-sm text-slate-500">Total Staff</p><p class="mt-2 text-2xl font-semibold">{{ $total }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Active</p><p class="mt-2 text-2xl font-semibold">{{ $active }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Departments</p><p class="mt-2 text-2xl font-semibold">{{ $departmentCounts->count() }}</p></x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card><h3 class="font-semibold">Gender</h3>@foreach($genderCounts as $label => $count)<div class="mt-2 flex justify-between text-sm"><span>{{ $label }}</span><span>{{ $count }}</span></div>@endforeach</x-card>
        <x-card><h3 class="font-semibold">Departments</h3>@foreach($departmentCounts as $label => $count)<div class="mt-2 flex justify-between text-sm"><span>{{ $label }}</span><span>{{ $count }}</span></div>@endforeach</x-card>
        <x-card><h3 class="font-semibold">Employment Category</h3>@foreach($categoryCounts as $label => $count)<div class="mt-2 flex justify-between text-sm"><span>{{ $label }}</span><span>{{ $count }}</span></div>@endforeach</x-card>
    </div>
</div>
