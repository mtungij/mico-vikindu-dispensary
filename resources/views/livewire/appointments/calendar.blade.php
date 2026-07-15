<div class="space-y-4">
    <x-card>
        <div class="grid gap-3 lg:grid-cols-6">
            <select wire:model.live="view" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>
            <input type="date" wire:model.live="date" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <select wire:model.live="department_id" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>
            <select wire:model.live="staff_id" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Doctor</option>@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
            <select wire:model.live="status" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Status</option><option value="booked">Booked</option><option value="checked_in">Checked In</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
            <a href="{{ route('appointments.create') }}" class="rounded-md bg-primary px-3 py-2 text-center text-sm font-semibold text-white">Book</a>
        </div>
    </x-card>
    <div class="grid gap-3 lg:grid-cols-2">
        @forelse($appointments as $appointment)
            <x-card>
                <div class="flex items-start justify-between gap-3">
                    <div><p class="font-semibold">{{ $appointment->appointment_date?->format('Y-m-d') }} {{ $appointment->appointment_time ? substr($appointment->appointment_time,0,5) : '' }}</p><p class="mt-1 text-sm text-slate-500">{{ $appointment->patient?->fullName() }} · {{ $appointment->department?->name }}</p></div>
                    <span class="text-sm">{{ str($appointment->status?->value ?? $appointment->status)->headline() }}</span>
                </div>
            </x-card>
        @empty
            <x-card><p class="py-8 text-center text-sm text-slate-500">No appointments in this calendar range.</p></x-card>
        @endforelse
    </div>
</div>
