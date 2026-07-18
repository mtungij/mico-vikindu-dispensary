<div class="space-y-4">
    @error('session')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">{{ $message }}</div>
    @enderror

    <x-card>
        @if($activeSession)
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm text-slate-500">Active Session</p>
                    <h3 class="text-lg font-semibold">{{ $activeSession->session_number }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $activeSession->cashier?->name ?? auth()->user()->name }} · {{ str($activeSession->shift ?? 'morning')->headline() }} · {{ $activeSession->opened_at?->format('Y-m-d H:i') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('cashier.sessions.show', $activeSession) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Current Session</a>
                    @can('close', $activeSession)
                        <button type="button" wire:click="$set('showClose', true)" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Close Session</button>
                    @endcan
                </div>
            </div>

            <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><span class="text-slate-500">Opening Float</span><p class="font-semibold">{{ number_format($activeSession->opening_float, 2) }}</p></div>
                <div><span class="text-slate-500">Cash Collected</span><p class="font-semibold">{{ number_format($totals['cash'] ?? 0, 2) }}</p></div>
                <div><span class="text-slate-500">Non-cash Collected</span><p class="font-semibold">{{ number_format($totals['non_cash'] ?? 0, 2) }}</p></div>
                <div><span class="text-slate-500">Expected Cash</span><p class="font-semibold">{{ number_format($expected, 2) }}</p></div>
                <div><span class="text-slate-500">Refunds</span><p class="font-semibold">{{ number_format(0, 2) }}</p></div>
                <div><span class="text-slate-500">Reversals</span><p class="font-semibold">{{ number_format(0, 2) }}</p></div>
                <div><span class="text-slate-500">Cash Drawer</span><p class="font-semibold">{{ $activeSession->cash_drawer ?? 'Main Counter' }}</p></div>
                <div><span class="text-slate-500">Status</span><p class="font-semibold">{{ $activeSession->status }}</p></div>
            </div>
        @else
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="font-semibold">Huna cashier session iliyo wazi.</h3>
                    <p class="mt-1 text-sm text-slate-500">Unaweza kufungua session kwa ufuatiliaji wa shift na cash drawer. Malipo yanaweza kupokelewa bila session.</p>
                </div>
                @can('create', \App\Models\CashierSession::class)
                    <button type="button" wire:click="create" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Fungua Cashier Session</button>
                @endcan
            </div>
        @endif
    </x-card>

    <x-card>
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h3 class="font-semibold">Session History</h3>
                <p class="mt-1 text-sm text-slate-500">Cashiers see their own sessions; supervisors can view all sessions.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
                <input type="date" wire:model.live="dateFilter" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">All statuses</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                    <option value="variance_review">Variance</option>
                </select>
                <select wire:model.live="shiftFilter" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">All shifts</option>
                    <option value="morning">Morning</option>
                    <option value="afternoon">Afternoon</option>
                    <option value="evening">Evening</option>
                    <option value="night">Night</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Session Number</th>
                        <th class="px-3 py-2">Cashier</th>
                        <th class="px-3 py-2">Shift</th>
                        <th class="px-3 py-2">Opened</th>
                        <th class="px-3 py-2">Closed</th>
                        <th class="px-3 py-2 text-right">Opening Float</th>
                        <th class="px-3 py-2 text-right">Expected Cash</th>
                        <th class="px-3 py-2 text-right">Declared Cash</th>
                        <th class="px-3 py-2 text-right">Variance</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3"><a class="font-semibold text-primary" href="{{ route('cashier.sessions.show', $session) }}">{{ $session->session_number }}</a></td>
                            <td class="px-3 py-3">{{ $session->cashier?->name }}</td>
                            <td class="px-3 py-3">{{ str($session->shift ?? 'morning')->headline() }}</td>
                            <td class="px-3 py-3">{{ $session->opened_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-3">{{ $session->closed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($session->opening_float, 2) }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($session->expected_cash ?? 0, 2) }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($session->declared_cash ?? 0, 2) }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($session->variance ?? 0, 2) }}</td>
                            <td class="px-3 py-3">{{ $session->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-8 text-center text-slate-500">Hakuna cashier sessions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $sessions->links() }}</div>
    </x-card>

    @if($showOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl dark:bg-card-dark">
                <h3 class="mb-4 font-semibold">Open Cashier Session</h3>
                <form wire:submit="openSession" class="space-y-4">
                    @error('session')<p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    <label class="block">
                        <span class="text-sm">Shift</span>
                        <select wire:model="openForm.shift" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                            <option value="custom">Custom</option>
                        </select>
                        @error('openForm.shift')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Opening Float</span>
                        <input type="number" step="0.01" min="0" wire:model="openForm.opening_float" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('openForm.opening_float')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Cash Drawer</span>
                        <input type="text" wire:model="openForm.cash_drawer" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('openForm.cash_drawer')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Notes</span>
                        <textarea wire:model="openForm.notes" rows="3" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></textarea>
                        @error('openForm.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</button>
                        <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="openSession">
                            <span wire:loading.remove wire:target="openSession">Open Session</span>
                            <span wire:loading wire:target="openSession">Inafungua...</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showClose)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl dark:bg-card-dark">
                <h3 class="mb-4 font-semibold">Close Cashier Session</h3>
                <form wire:submit="close" class="space-y-4">
                    <label class="block">
                        <span class="text-sm">Declared Cash</span>
                        <input type="number" step="0.01" min="0" wire:model="declared_cash" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('declared_cash')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Variance Reason</span>
                        <textarea wire:model="variance_reason" rows="3" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></textarea>
                        @error('variance_reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</button>
                        <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="close">Close Session</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
