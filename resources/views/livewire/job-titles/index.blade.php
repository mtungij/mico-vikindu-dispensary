<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[720px]">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta cheo..." />
            <x-select-input wire:model.live="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="all">Zote</option>
            </x-select-input>
            <x-select-input wire:model.live="department">
                <option value="">Departments zote</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div class="flex gap-2">
            <x-secondary-button wire:click="resetFilters"><x-lucide-rotate-ccw class="h-4 w-4" /> Reset</x-secondary-button>
            @can('create', \App\Models\JobTitle::class)
                <x-primary-button wire:click="create"><x-lucide-plus class="h-4 w-4" /> Ongeza</x-primary-button>
            @endcan
        </div>
    </div>

    @error('delete')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $message }}</div>
    @enderror

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="px-4 py-3">Cheo</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">License</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($jobTitles as $jobTitle)
                        <tr wire:key="job-title-{{ $jobTitle->id }}">
                            <td class="px-4 py-3">
                                <span class="block font-semibold">{{ $jobTitle->name }}</span>
                                <span class="text-xs text-slate-500">{{ $jobTitle->code }} - {{ $jobTitle->users_count }} watumishi</span>
                            </td>
                            <td class="px-4 py-3">{{ $jobTitle->department?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $jobTitle->employment_category?->label() ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $jobTitle->requires_professional_license ? ($jobTitle->license_authority ?: 'Required') : 'No' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <x-badge :tone="$jobTitle->is_active ? 'success' : 'danger'">{{ $jobTitle->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                    @if($jobTitle->is_clinical)<x-badge tone="info">Clinical</x-badge>@endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @can('activate', $jobTitle)
                                        <button type="button" wire:click="toggleStatus({{ $jobTitle->id }})" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Badili status"><x-lucide-power class="h-4 w-4" /></button>
                                    @endcan
                                    @can('update', $jobTitle)
                                        <button type="button" wire:click="edit({{ $jobTitle->id }})" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Hariri"><x-lucide-pencil class="h-4 w-4" /></button>
                                    @endcan
                                    @can('delete', $jobTitle)
                                        <button type="button" wire:click="delete({{ $jobTitle->id }})" wire:confirm="Una uhakika unataka kufuta cheo hiki?" class="rounded-md p-2 text-danger hover:bg-red-50 dark:hover:bg-red-950/30" title="Futa"><x-lucide-trash-2 class="h-4 w-4" /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10"><x-empty-state icon="briefcase-medical" title="Hakuna vyeo" message="Vyeo vya kazi vitaonekana hapa baada ya kusajiliwa." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $jobTitles->links() }}</div>
    </x-card>

    <x-modal :show="$showFormModal" :title="$editing ? 'Hariri Cheo' : 'Ongeza Cheo'" close="closeFormModal" maxWidth="4xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Jina" /><x-text-input wire:model="form.name" /><x-input-error :messages="$errors->get('form.name')" class="mt-1" /></div>
                <div><x-input-label value="Code" /><x-text-input wire:model="form.code" /><x-input-error :messages="$errors->get('form.code')" class="mt-1" /></div>
                <div>
                    <x-input-label value="Department" />
                    <x-select-input wire:model="form.department_id">
                        <option value="">Bila department</option>
                        @foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Employment Category" />
                    <x-select-input wire:model="form.employment_category">
                        <option value="">Chagua</option>
                        @foreach ($employmentCategories as $category)<option value="{{ $category->value }}">{{ $category->label() }}</option>@endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Minimum Education" />
                    <x-select-input wire:model="form.minimum_education_level">
                        <option value="">Chagua</option>
                        @foreach ($educationLevels as $level)<option value="{{ $level->value }}">{{ $level->label() }}</option>@endforeach
                    </x-select-input>
                </div>
                <div><x-input-label value="License Authority" /><x-text-input wire:model="form.license_authority" /><x-input-error :messages="$errors->get('form.license_authority')" class="mt-1" /></div>
                <div><x-input-label value="Sort Order" /><x-text-input type="number" min="0" wire:model="form.sort_order" /></div>
            </div>
            <div><x-input-label value="Maelezo" /><x-textarea wire:model="form.description" rows="3" /></div>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.requires_professional_license" /> Requires License</label>
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.is_clinical" /> Clinical</label>
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.is_active" /> Active</label>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button wire:click="closeFormModal">Ghairi</x-secondary-button>
                <x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
