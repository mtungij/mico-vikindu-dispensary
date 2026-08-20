<x-layouts.app title="Dispensing Medicine Labels" description="Prepare and print medicine labels for the patient.">
    <x-slot:actions>
        <a href="{{ route('pharmacy.index') }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
            <x-lucide-arrow-left class="h-4 w-4" /> Back to Pharmacy
        </a>
        <a href="{{ route('pharmacy.dispensings.labels.print', $dispensing) }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800">
            <x-lucide-printer class="h-4 w-4" /> Print All Labels
        </a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(420px,1.1fr)]">
        <div class="space-y-6">
            <x-card>
                <div class="mb-5 flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-primary/20"><x-lucide-user-round class="h-5 w-5" /></span>
                    <h2 class="text-lg font-semibold">Patient Information</h2>
                </div>
                <div class="flex items-center gap-4">
                    @if ($dispensing->patient?->passport_photo_path)
                        <img src="{{ Storage::url($dispensing->patient->passport_photo_path) }}" alt="{{ $dispensing->patient->fullName() }}" class="h-20 w-20 rounded-full border-2 border-white object-cover shadow ring-1 ring-slate-200 dark:border-slate-800 dark:ring-slate-700">
                    @else
                        <div data-testid="patient-initials" class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-primary text-xl font-bold text-white shadow-sm">{{ $dispensing->patient?->initials() }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-xl font-semibold text-slate-950 dark:text-white">{{ $dispensing->patient?->fullName() }}</p>
                        <p class="mt-1 text-sm font-medium text-primary">{{ $dispensing->patient?->patient_number }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $dispensing->patient?->ageLabel() }} · {{ $dispensing->patient?->gender?->label() }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="mb-5 flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-primary/20"><x-lucide-clipboard-check class="h-5 w-5" /></span>
                    <h2 class="text-lg font-semibold">Dispensing Information</h2>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500 dark:text-slate-400">Dispensing number</dt><dd class="mt-1 font-semibold">{{ $dispensing->dispensing_number }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Dispensed</dt><dd class="mt-1 font-semibold">{{ $dispensing->dispensed_at?->format('d M Y H:i') }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Location</dt><dd class="mt-1 font-semibold">{{ $dispensing->location?->name ?? 'Pharmacy' }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Dispensed by</dt><dd class="mt-1 font-semibold">{{ $dispensing->dispenser?->fullStaffName() ?? '—' }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <div class="mb-5 flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-primary/20"><x-lucide-pill class="h-5 w-5" /></span>
                    <div><h2 class="text-lg font-semibold">Medicine List</h2><p class="text-xs text-slate-500">{{ $dispensing->items->count() }} {{ Str::plural('label', $dispensing->items->count()) }}</p></div>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($dispensing->items as $item)
                        @php
                            $quantity = (float) $item->dispensed_quantity;
                            $quantityLabel = floor($quantity) === $quantity ? number_format($quantity, 0) : rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
                        @endphp
                        <article class="flex flex-col gap-4 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-950 dark:text-white">{{ $item->medicine?->name }}</h3>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-medium text-slate-700 dark:text-slate-200">Qty: {{ $quantityLabel }}</span>
                                    <x-badge :tone="in_array($item->status, ['dispensed', 'completed']) ? 'success' : 'warning'">{{ str($item->status)->replace('_', ' ')->title() }}</x-badge>
                                    @if (filled($item->prescriptionItem?->dose))<span class="text-slate-500 dark:text-slate-400">{{ $item->prescriptionItem->dose }} · {{ $item->prescriptionItem->frequency }}</span>@endif
                                </div>
                            </div>
                            <a href="{{ route('pharmacy.dispensings.labels.item.print', [$dispensing, $item]) }}" target="_blank" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-primary/30 px-3 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5 dark:hover:bg-primary/10">
                                <x-lucide-printer class="h-4 w-4" /> Print Medicine Label
                            </a>
                        </article>
                    @endforeach
                </div>
            </x-card>
        </div>

        
    </div>
</x-layouts.app>
