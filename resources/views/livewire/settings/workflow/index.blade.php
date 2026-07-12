<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($workflowSettings as $setting)
            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <span class="pr-4">
                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ str($setting->key)->replace('_', ' ')->title() }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Facility workflow rule</span>
                </span>
                <input type="checkbox" wire:model="settings.{{ $setting->key }}" class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
            </label>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Department Queues</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Department</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Prefix</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Active</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Print Tickets</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Display</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($departmentQueues as $queue)
                        <tr>
                            <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $queue->department?->name }}</td>
                            <td class="px-4 py-3"><input wire:model="queues.{{ $queue->id }}.queue_prefix" class="w-24 rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"></td>
                            <td class="px-4 py-3"><input type="checkbox" wire:model="queues.{{ $queue->id }}.is_active" class="rounded border-slate-300 text-blue-600"></td>
                            <td class="px-4 py-3"><input type="checkbox" wire:model="queues.{{ $queue->id }}.print_tickets" class="rounded border-slate-300 text-blue-600"></td>
                            <td class="px-4 py-3"><input type="checkbox" wire:model="queues.{{ $queue->id }}.display_screen_enabled" class="rounded border-slate-300 text-blue-600"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-end border-t border-slate-200 px-4 py-3 dark:border-slate-700">
            <button wire:click="save" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Settings</button>
        </div>
    </div>
</div>
