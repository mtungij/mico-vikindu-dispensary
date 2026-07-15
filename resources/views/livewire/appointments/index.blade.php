<div class="space-y-4">
    <x-card>
        <div class="grid gap-3 lg:grid-cols-8">
            <input wire:model.live.debounce.300ms="search" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" placeholder="Search appointment, patient, phone, doctor">
            <input type="date" wire:model.live="date" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <select wire:model.live="department_id" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>
            <select wire:model.live="staff_id" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Provider</option>@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
            <select wire:model.live="status" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Status</option><option value="booked">Booked</option><option value="confirmed">Confirmed</option><option value="checked_in">Checked in</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="no_show">No show</option></select>
            <select wire:model.live="type" class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Type</option><option value="general_consultation">General</option><option value="follow_up_visit">Follow-up</option><option value="dental">Dental</option><option value="anc">ANC</option><option value="laboratory">Laboratory</option></select>
            <a href="{{ route('appointments.calendar') }}" class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold dark:border-slate-700">Calendar</a>
            <a href="{{ route('appointments.book') }}" class="rounded-md bg-primary px-3 py-2 text-center text-sm font-semibold text-white">Book Appointment</a>
        </div>
    </x-card>
    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Appointment Number</th><th class="px-3 py-2">Patient</th><th class="px-3 py-2">Department</th><th class="px-3 py-2">Provider</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Time</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Confirmation</th><th class="px-3 py-2"></th></tr></thead>
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3 font-semibold">{{ $appointment->appointment_number }}</td>
                            <td class="px-3 py-3">{{ $appointment->patient?->fullName() }}</td>
                            <td class="px-3 py-3">{{ $appointment->department?->name }}</td>
                            <td class="px-3 py-3">{{ $appointment->staff?->name }}</td>
                            <td class="px-3 py-3">{{ str($appointment->appointment_type?->value ?? $appointment->appointment_type)->headline() }}</td>
                            <td class="px-3 py-3">{{ $appointment->appointment_date?->format('Y-m-d') ?? $appointment->scheduled_start?->format('Y-m-d') }}</td>
                            <td class="px-3 py-3">{{ $appointment->appointment_time ? substr($appointment->appointment_time,0,5) : $appointment->scheduled_start?->format('H:i') }}</td>
                            <td class="px-3 py-3">{{ str($appointment->status?->value ?? $appointment->status)->headline() }}</td>
                            <td class="px-3 py-3">
                                @if(($appointment->status?->value ?? $appointment->status) === 'confirmed')
                                    <span class="text-xs font-semibold text-emerald-600">Confirmed</span>
                                @else
                                    <button type="button" wire:click="confirm({{ $appointment->id }})" class="rounded-md border px-2 py-1 text-xs dark:border-slate-700">Confirm</button>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('appointments.edit', $appointment) }}" class="rounded-md border px-2 py-1 text-xs dark:border-slate-700">Edit</a>
                                    <button wire:click="checkIn({{ $appointment->id }})" class="rounded-md bg-primary px-2 py-1 text-xs font-semibold text-white">Check In</button>
                                    <button wire:click="rescheduleNextDay({{ $appointment->id }})" class="rounded-md border px-2 py-1 text-xs dark:border-slate-700">Reschedule</button>
                                    <button wire:click="noShow({{ $appointment->id }})" class="rounded-md border px-2 py-1 text-xs dark:border-slate-700">No-show</button>
                                    <button wire:click="cancel({{ $appointment->id }})" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-600">Cancel</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-8 text-center text-slate-500">No appointments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $appointments->links() }}</div>
    </x-card>
</div>
