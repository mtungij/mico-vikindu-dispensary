<div class="space-y-4">
    <div class="flex justify-end">
        <button type="button" wire:click="create" class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white"><x-lucide-calendar-plus class="h-4 w-4" /> Add Doctor Schedule</button>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Provider</th><th class="px-3 py-2">Department</th><th class="px-3 py-2">Day</th><th class="px-3 py-2">Working Hours</th><th class="px-3 py-2">Break</th><th class="px-3 py-2">Slot</th><th class="px-3 py-2">Max Patients</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr></thead>
                <tbody>
                    @forelse($schedules as $schedule)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3 font-semibold">{{ $schedule->staff?->name }}</td>
                            <td class="px-3 py-3">{{ $schedule->department?->name ?? 'Any' }}</td>
                            <td class="px-3 py-3">{{ str($schedule->working_day)->headline() }}</td>
                            <td class="px-3 py-3">{{ substr((string) $schedule->start_time, 0, 5) }} - {{ substr((string) $schedule->end_time, 0, 5) }}</td>
                            <td class="px-3 py-3">{{ substr((string) $schedule->break_start, 0, 5) }} - {{ substr((string) $schedule->break_end, 0, 5) }}</td>
                            <td class="px-3 py-3">{{ $schedule->slot_duration }} min</td>
                            <td class="px-3 py-3">{{ $schedule->max_patients_per_day }} / day</td>
                            <td class="px-3 py-3">{{ $schedule->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-3 py-3 text-right"><button type="button" wire:click="edit({{ $schedule->id }})" class="rounded-md border px-2 py-1 text-xs dark:border-slate-700">Edit</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">No doctor schedules.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-md bg-white p-5 shadow-xl dark:bg-card-dark">
                <h3 class="mb-4 font-semibold">{{ $editingId ? 'Edit Doctor Schedule' : 'Add Doctor Schedule' }}</h3>
                <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                    <label class="block"><span class="text-sm">Provider</span><select wire:model="staff_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Select</option>@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>@error('staff_id')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</label>
                    <label class="block"><span class="text-sm">Department</span><select wire:model="department_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="">Any</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>@error('department_id')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</label>
                    <label class="block"><span class="text-sm">Working Day</span><select wire:model="working_day" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><option value="monday">Monday</option><option value="tuesday">Tuesday</option><option value="wednesday">Wednesday</option><option value="thursday">Thursday</option><option value="friday">Friday</option><option value="saturday">Saturday</option><option value="sunday">Sunday</option></select>@error('working_day')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</label>
                    <label class="block"><span class="text-sm">Slot Duration</span><input type="number" min="5" step="5" wire:model="slot_duration" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">@error('slot_duration')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</label>
                    <label class="block"><span class="text-sm">Start Time</span><input type="time" wire:model="start_time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="block"><span class="text-sm">End Time</span><input type="time" wire:model="end_time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="block"><span class="text-sm">Break Start</span><input type="time" wire:model="break_start" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="block"><span class="text-sm">Break End</span><input type="time" wire:model="break_end" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="block"><span class="text-sm">Maximum Patients Per Day</span><input type="number" wire:model="max_patients_per_day" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="block"><span class="text-sm">Maximum Patients Per Hour</span><input type="number" wire:model="max_patients_per_hour" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="block md:col-span-2"><span class="text-sm">Unavailable Dates</span><input wire:model="unavailable_dates" placeholder="2026-07-20, 2026-07-21" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300"> Active</label>
                    <div class="flex justify-end gap-2 md:col-span-2"><button type="button" wire:click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</button><x-primary-button type="submit" wire:loading.attr="disabled" wire:target="save">Save Schedule</x-primary-button></div>
                </form>
            </div>
        </div>
    @endif
</div>
