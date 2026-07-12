<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <input wire:model.live.debounce.300ms="search" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 sm:max-w-sm" placeholder="Tafuta...">
        <button wire:click="create" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Ongeza</button>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Code</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $row->name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row->code }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $row->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $row->id }})" class="rounded-md px-2 py-1 text-blue-600 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950">Edit</button>
                                <button wire:click="toggle({{ $row->id }})" class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">{{ $row->is_active ? 'Disable' : 'Enable' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Hakuna records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $rows->links() }}</div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $editingId ? 'Edit' : 'Create' }} {{ $title }}</h2>
                    <button wire:click="$set('showModal', false)" class="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">x</button>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2">
                    @foreach($fields as $field => $meta)
                        <div class="{{ ($meta['type'] ?? 'text') === 'textarea' ? 'md:col-span-2' : '' }}">
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ str($field)->replace('_',' ')->title() }}</label>
                            @if(($meta['type'] ?? 'text') === 'textarea')
                                <textarea wire:model="form.{{ $field }}" rows="3" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"></textarea>
                            @elseif(($meta['type'] ?? 'text') === 'checkbox')
                                <input type="checkbox" wire:model="form.{{ $field }}" class="rounded border-slate-300 text-blue-600">
                            @elseif(($meta['type'] ?? 'text') === 'select')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                    @foreach($meta['options'] as $option)<option value="{{ $option }}">{{ str($option)->replace('_',' ')->title() }}</option>@endforeach
                                </select>
                            @elseif(($meta['type'] ?? 'text') === 'procedure_type')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"><option value="">Chagua</option>@foreach($procedureTypes as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'service')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"><option value="">None</option>@foreach($services as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'anaesthetic')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"><option value="">None</option>@foreach($anaesthetics as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'consent_template')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"><option value="">None</option>@foreach($consentTemplates as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'room')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"><option value="">Chagua</option>@foreach($rooms as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                            @else
                                <input type="{{ ($meta['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" wire:model="form.{{ $field }}" class="w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                            @endif
                            @error('form.'.$field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                    <button wire:click="$set('showModal', false)" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700 dark:text-slate-200">Cancel</button>
                    <button wire:click="save" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
