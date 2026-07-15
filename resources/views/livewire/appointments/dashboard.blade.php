<div class="space-y-4">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('appointments.book') }}" class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white"><x-lucide-calendar-plus class="h-4 w-4" /> Book Appointment</a>
        <a href="{{ route('appointments.calendar') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold dark:border-slate-700"><x-lucide-calendar class="h-4 w-4" /> Calendar</a>
        <a href="{{ route('appointments.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold dark:border-slate-700"><x-lucide-calendar-check class="h-4 w-4" /> All Appointments</a>
    </div>
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach($cards as $label => $value)
            <x-card><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold">{{ $value }}</p></x-card>
        @endforeach
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="font-semibold">Department Summary</h3>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($departmentSummary as $department => $count)
                    <div class="flex justify-between py-2 text-sm"><span>{{ $department ?: 'Unassigned' }}</span><span class="font-semibold">{{ $count }}</span></div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No department activity today.</p>
                @endforelse
            </div>
        </x-card>
        <x-card>
            <h3 class="font-semibold">Provider Summary</h3>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($providerSummary as $provider => $count)
                    <div class="flex justify-between py-2 text-sm"><span>{{ $provider }}</span><span class="font-semibold">{{ $count }}</span></div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No provider activity today.</p>
                @endforelse
            </div>
        </x-card>
    </div>
    <x-card>
        <div class="flex items-center justify-between"><h3 class="font-semibold">Today</h3><a href="{{ route('appointments.book') }}" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Book Appointment</a></div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3 font-semibold">{{ $appointment->appointment_time ? substr($appointment->appointment_time, 0, 5) : $appointment->scheduled_start?->format('H:i') }}</td>
                            <td class="px-3 py-3">{{ $appointment->patient?->fullName() }}</td>
                            <td class="px-3 py-3">{{ $appointment->department?->name }}</td>
                            <td class="px-3 py-3">{{ $appointment->staff?->name }}</td>
                            <td class="px-3 py-3">{{ str($appointment->status?->value ?? $appointment->status)->headline() }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-8 text-center text-slate-500">No appointments today.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
