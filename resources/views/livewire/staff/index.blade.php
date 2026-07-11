<div class="space-y-6">
    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="grid gap-3 md:grid-cols-3 xl:min-w-[760px]">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta mtumishi..." />
            <x-select-input wire:model.live="department">
                <option value="">Departments zote</option>
                @foreach ($departments as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
            </x-select-input>
            <x-select-input wire:model.live="jobTitle">
                <option value="">Vyeo vyote</option>
                @foreach ($jobTitles as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
            </x-select-input>
            <x-select-input wire:model.live="role">
                <option value="">Roles zote</option>
                @foreach ($roles as $item)<option value="{{ $item->id }}">{{ $item->display_name ?? $item->name }}</option>@endforeach
            </x-select-input>
            <x-select-input wire:model.live="employmentStatus">
                <option value="">Ajira zote</option>
                @foreach ($employmentStatuses as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach
            </x-select-input>
            <x-select-input wire:model.live="accountStatus">
                <option value="">Accounts zote</option>
                @foreach ($accountStatuses as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach
            </x-select-input>
        </div>
        <div class="flex gap-2">
            <x-select-input wire:model.live="perPage" class="w-24">
                <option>10</option><option>25</option><option>50</option><option>100</option>
            </x-select-input>
            @can('create', \App\Models\StaffProfile::class)
                <x-primary-button wire:click="create"><x-lucide-user-plus class="h-4 w-4" /> {{ __('staff.add_staff') }}</x-primary-button>
            @endcan
        </div>
    </div>

    <x-card>
        <div class="hidden overflow-x-auto lg:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3">Namba</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Cheo</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Ajira</th>
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">License</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($staff as $profile)
                        @php($license = $compliance->getLicenseStatus($profile))
                        <tr wire:key="staff-row-{{ $profile->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-md bg-primary text-sm font-semibold text-white">{{ $profile->initials() }}</div>
                                    <span>
                                        <a href="{{ route('staff.show', $profile) }}" class="block font-semibold hover:text-primary">{{ $profile->fullName() }}</a>
                                        <span class="text-xs text-slate-500">{{ $profile->user->email }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $profile->employee_number }}</td>
                            <td class="px-4 py-3">{{ $profile->employmentRecord?->primaryDepartment?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $profile->employmentRecord?->jobTitle?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $profile->user->roles->pluck('display_name')->filter()->take(2)->implode(', ') ?: $profile->user->roles->pluck('name')->take(2)->implode(', ') }}</td>
                            <td class="px-4 py-3">{{ $profile->primary_phone }}</td>
                            <td class="px-4 py-3"><x-badge :tone="$profile->employmentRecord?->employment_status?->badge() ?? 'warning'">{{ $profile->employmentRecord?->employment_status?->label() ?? '-' }}</x-badge></td>
                            <td class="px-4 py-3"><x-badge :tone="$profile->user->status->badge()">{{ $profile->user->status->label() }}</x-badge></td>
                            <td class="px-4 py-3">
                                <x-badge :tone="$license === 'ok' ? 'success' : ($license === 'expiring' ? 'warning' : 'danger')">{{ str($license)->replace('_', ' ')->title() }}</x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('staff.show', $profile) }}" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="View"><x-lucide-eye class="h-4 w-4" /></a>
                                    @can('manageSignature', $profile)
                                        <a href="{{ route('staff.show', ['staffProfile' => $profile, 'tab' => 'signature']) }}" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Signature"><x-lucide-signature class="h-4 w-4" /></a>
                                    @endcan
                                    @can('activate', $profile)
                                        <button wire:click="activate({{ $profile->id }})" class="rounded-md p-2 text-success hover:bg-green-50 dark:hover:bg-green-950/30" title="Activate"><x-lucide-circle-check class="h-4 w-4" /></button>
                                    @endcan
                                    @can('suspend', $profile)
                                        <button wire:click="suspend({{ $profile->id }})" wire:confirm="Simamisha account hii?" class="rounded-md p-2 text-warning hover:bg-amber-50 dark:hover:bg-amber-950/30" title="Suspend"><x-lucide-ban class="h-4 w-4" /></button>
                                    @endcan
                                    @can('delete', $profile)
                                        <button wire:click="deactivate({{ $profile->id }})" wire:confirm="Deactivate account hii?" class="rounded-md p-2 text-danger hover:bg-red-50 dark:hover:bg-red-950/30" title="Deactivate"><x-lucide-user-x class="h-4 w-4" /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-10"><x-empty-state icon="users" title="Hakuna watumishi" message="Watumishi wataonekana hapa baada ya kusajiliwa." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 lg:hidden">
            @forelse ($staff as $profile)
                <div class="rounded-md border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-primary text-sm font-semibold text-white">{{ $profile->initials() }}</div>
                            <div>
                                <a href="{{ route('staff.show', $profile) }}" class="font-semibold">{{ $profile->fullName() }}</a>
                                <p class="text-xs text-slate-500">{{ $profile->employee_number }} - {{ $profile->user->email }}</p>
                            </div>
                        </div>
                        <x-badge :tone="$profile->user->status->badge()">{{ $profile->user->status->label() }}</x-badge>
                    </div>
                    <div class="mt-3 grid gap-2 text-sm">
                        <p>{{ $profile->employmentRecord?->primaryDepartment?->name ?? '-' }} / {{ $profile->employmentRecord?->jobTitle?->name ?? '-' }}</p>
                        <p>{{ $profile->primary_phone }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state icon="users" title="Hakuna watumishi" message="Watumishi wataonekana hapa baada ya kusajiliwa." />
            @endforelse
        </div>
        <div class="mt-4">{{ $staff->links() }}</div>
    </x-card>

    <x-modal :show="$showCreateModal" title="Sajili Mtumishi" close="closeCreateModal" maxWidth="6xl">
        <div class="mb-5 grid gap-2 sm:grid-cols-6">
            @foreach ([1 => 'Binafsi', 2 => 'Ajira', 3 => 'Account', 4 => 'Elimu', 5 => 'Nyaraka', 6 => 'Hakiki'] as $number => $label)
                <button type="button" wire:click="$set('step', {{ $number }})" class="rounded-md px-3 py-2 text-xs font-semibold {{ $step === $number ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $number }}. {{ $label }}</button>
            @endforeach
        </div>

        <form wire:submit="save" class="space-y-5">
            @if ($step === 1)
                <div class="grid gap-4 md:grid-cols-3">
                    <div><x-input-label value="First name" /><x-text-input wire:model="personal.first_name" /><x-input-error :messages="$errors->get('personal.first_name')" /></div>
                    <div><x-input-label value="Middle name" /><x-text-input wire:model="personal.middle_name" /></div>
                    <div><x-input-label value="Last name" /><x-text-input wire:model="personal.last_name" /><x-input-error :messages="$errors->get('personal.last_name')" /></div>
                    <div><x-input-label value="Gender" /><x-select-input wire:model="personal.gender"><option value="">Chagua</option>@foreach($genders as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</x-select-input></div>
                    <div><x-input-label value="Date of birth" /><x-text-input type="date" wire:model="personal.date_of_birth" /></div>
                    <div><x-input-label value="Marital status" /><x-select-input wire:model="personal.marital_status"><option value="">Chagua</option>@foreach($maritalStatuses as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</x-select-input></div>
                    <div><x-input-label value="Primary phone" /><x-text-input wire:model="personal.primary_phone" /><x-input-error :messages="$errors->get('personal.primary_phone')" /></div>
                    <div><x-input-label value="Secondary phone" /><x-text-input wire:model="personal.secondary_phone" /></div>
                    <div><x-input-label value="Personal email" /><x-text-input wire:model="personal.personal_email" /></div>
                    <div><x-input-label value="NIDA" /><x-text-input wire:model="personal.nida_number" /></div>
                    <div><x-input-label value="Passport number" /><x-text-input wire:model="personal.passport_number" /></div>
                    <div><x-input-label value="Nationality" /><x-text-input wire:model="personal.nationality" /></div>
                    <div><x-input-label value="Region" /><x-text-input wire:model="personal.region" /></div>
                    <div><x-input-label value="District" /><x-text-input wire:model="personal.district" /></div>
                    <div><x-input-label value="Ward" /><x-text-input wire:model="personal.ward" /></div>
                </div>
                <x-file-upload label="Passport photo" model="passportPhoto" accept="image/png,image/jpeg,image/webp" hint="JPG, PNG au WEBP, max 2MB" />
            @endif

            @if ($step === 2)
                <div class="grid gap-4 md:grid-cols-3">
                    @can('staff.override-employee-number')
                        <div><x-input-label value="Employee number" /><x-text-input wire:model="personal.employee_number" /></div>
                    @endcan
                    <div><x-input-label value="Job title" /><x-select-input wire:model="employment.job_title_id"><option value="">Chagua</option>@foreach($jobTitles as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select-input></div>
                    <div><x-input-label value="Primary department" /><x-select-input wire:model="employment.primary_department_id"><option value="">Chagua</option>@foreach($departments as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select-input></div>
                    <div><x-input-label value="Employment category" /><x-select-input wire:model="employment.employment_category"><option value="">Chagua</option>@foreach($employmentCategories as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</x-select-input></div>
                    <div><x-input-label value="Employment status" /><x-select-input wire:model="employment.employment_status">@foreach($employmentStatuses as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</x-select-input></div>
                    <div><x-input-label value="Start date" /><x-text-input type="date" wire:model="employment.employment_start_date" /></div>
                    <div><x-input-label value="Contract start" /><x-text-input type="date" wire:model="employment.contract_start_date" /></div>
                    <div><x-input-label value="Contract end" /><x-text-input type="date" wire:model="employment.contract_end_date" /></div>
                    <div><x-input-label value="Payroll number" /><x-text-input wire:model="employment.payroll_number" /></div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Additional departments</h3><x-secondary-button wire:click="addAdditionalDepartment"><x-lucide-plus class="h-4 w-4" /> Ongeza</x-secondary-button></div>
                    @foreach($additionalDepartments as $index => $row)
                        <div class="grid gap-3 rounded-md border border-slate-200 p-3 md:grid-cols-4 dark:border-slate-700">
                            <x-select-input wire:model="additionalDepartments.{{ $index }}.department_id"><option value="">Department</option>@foreach($departments as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select-input>
                            <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="additionalDepartments.{{ $index }}.can_receive_queue" /> Queue</label>
                            <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="additionalDepartments.{{ $index }}.can_manage_department" /> Manage</label>
                            <x-secondary-button wire:click="removeAdditionalDepartment({{ $index }})"><x-lucide-trash-2 class="h-4 w-4" /> Ondoa</x-secondary-button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($step === 3)
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="account.create_login_account" /> Create login account</label>
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="account.must_change_password" /> Must change password</label>
                    <div><x-input-label value="Login email" /><x-text-input wire:model="account.email" /><x-input-error :messages="$errors->get('account.email')" /></div>
                    <div><x-input-label value="Account phone" /><x-text-input wire:model="account.phone" /></div>
                    <div><x-input-label value="Temporary password" /><x-text-input wire:model="account.temporary_password" /><x-input-error :messages="$errors->get('account.temporary_password')" /></div>
                    <div><x-input-label value="Confirm password" /><x-text-input wire:model="account.temporary_password_confirmation" /></div>
                    <div><x-input-label value="Account status" /><x-select-input wire:model="account.status">@foreach($accountStatuses as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</x-select-input></div>
                    <div class="flex items-end"><x-secondary-button wire:click="generatePassword"><x-lucide-key-round class="h-4 w-4" /> Generate</x-secondary-button></div>
                </div>
                <div>
                    <x-input-label value="Roles" />
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($roles as $item)
                            <label class="flex items-center gap-2 rounded-md border border-slate-200 p-2 text-sm dark:border-slate-700"><x-checkbox wire:model="account.role_ids" value="{{ $item->id }}" /> {{ $item->display_name ?? $item->name }}</label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('account.role_ids')" />
                </div>
                @can('staff.assign-direct-permission')
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach($permissions as $module => $items)
                            <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                                <p class="mb-2 text-sm font-semibold">{{ config("permissions.$module.label") ?? $module }}</p>
                                <div class="space-y-1">
                                    @foreach($items as $permission)
                                        <label class="flex items-center gap-2 text-xs"><x-checkbox wire:model="account.direct_permissions" value="{{ $permission->name }}" /> {{ $permission->label ?? $permission->name }}</label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endcan
            @endif

            @if ($step === 4)
                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="space-y-3 rounded-md border border-slate-200 p-4 dark:border-slate-700">
                        <h3 class="font-semibold">Education</h3>
                        <x-select-input wire:model="educationForm.education_level"><option value="">Education level</option>@foreach($educationLevels as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</x-select-input>
                        <x-text-input wire:model="educationForm.course_name" placeholder="Course" />
                        <x-text-input wire:model="educationForm.institution_name" placeholder="Institution" />
                        <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="educationForm.is_highest_qualification" /> Highest</label>
                        <x-secondary-button wire:click="addEducation"><x-lucide-plus class="h-4 w-4" /> Add Education</x-secondary-button>
                        @foreach($educationRecords as $record)<p class="text-sm text-slate-600 dark:text-slate-300">{{ $record['education_level'] }} - {{ $record['course_name'] }}</p>@endforeach
                    </div>
                    <div class="space-y-3 rounded-md border border-slate-200 p-4 dark:border-slate-700">
                        <h3 class="font-semibold">License</h3>
                        <x-text-input wire:model="licenseForm.license_type" placeholder="License type" />
                        <x-text-input wire:model="licenseForm.professional_body" placeholder="Professional body" />
                        <x-text-input wire:model="licenseForm.registration_number" placeholder="Registration number" />
                        <x-text-input type="date" wire:model="licenseForm.expiry_date" />
                        <x-secondary-button wire:click="addLicense"><x-lucide-plus class="h-4 w-4" /> Add License</x-secondary-button>
                        @foreach($licenses as $record)<p class="text-sm text-slate-600 dark:text-slate-300">{{ $record['professional_body'] }} - {{ $record['registration_number'] }}</p>@endforeach
                    </div>
                </div>
            @endif

            @if ($step === 5)
                <div class="space-y-3 rounded-md border border-slate-200 p-4 dark:border-slate-700">
                    <h3 class="font-semibold">Emergency Contact</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <x-text-input wire:model="contactForm.full_name" placeholder="Full name" />
                        <x-text-input wire:model="contactForm.relationship" placeholder="Relationship" />
                        <x-text-input wire:model="contactForm.primary_phone" placeholder="Primary phone" />
                        <x-text-input wire:model="contactForm.email" placeholder="Email" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="contactForm.is_primary" /> Primary contact</label>
                    <x-secondary-button wire:click="addEmergencyContact"><x-lucide-plus class="h-4 w-4" /> Add Contact</x-secondary-button>
                    @foreach($emergencyContacts as $record)<p class="text-sm text-slate-600 dark:text-slate-300">{{ $record['full_name'] }} - {{ $record['primary_phone'] }}</p>@endforeach
                </div>
            @endif

            @if ($step === 6)
                <div class="grid gap-4 md:grid-cols-2">
                    <x-card><h3 class="font-semibold">Binafsi</h3><p class="mt-2 text-sm">{{ $personal->first_name }} {{ $personal->middle_name }} {{ $personal->last_name }}</p><p class="text-sm">{{ $personal->primary_phone }}</p></x-card>
                    <x-card><h3 class="font-semibold">Ajira</h3><p class="mt-2 text-sm">Department ID: {{ $employment->primary_department_id ?: '-' }}</p><p class="text-sm">Job Title ID: {{ $employment->job_title_id ?: '-' }}</p></x-card>
                    <x-card><h3 class="font-semibold">Account</h3><p class="mt-2 text-sm">{{ $account->email }}</p><p class="text-sm">{{ count($account->role_ids) }} roles</p></x-card>
                    <x-card><h3 class="font-semibold">Records</h3><p class="mt-2 text-sm">{{ count($educationRecords) }} education, {{ count($licenses) }} licenses, {{ count($emergencyContacts) }} contacts</p></x-card>
                </div>
            @endif

            <div class="flex justify-between gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                <x-secondary-button wire:click="previousStep" :disabled="$step === 1"><x-lucide-arrow-left class="h-4 w-4" /> Nyuma</x-secondary-button>
                @if($step < 6)
                    <x-primary-button type="button" wire:click="nextStep">Endelea <x-lucide-arrow-right class="h-4 w-4" /></x-primary-button>
                @else
                    <x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi</x-primary-button>
                @endif
            </div>
        </form>
    </x-modal>

    <x-modal :show="$generatedPassword !== null" title="Temporary Password" maxWidth="md">
        <p class="text-sm text-slate-600 dark:text-slate-300">Nenosiri hili linaonekana mara moja tu.</p>
        <div class="mt-3 rounded-md bg-slate-100 p-3 font-mono text-sm dark:bg-slate-800">{{ $generatedPassword }}</div>
        <div class="mt-4 flex justify-end gap-2">
            @if($createdStaffUrl)<a href="{{ $createdStaffUrl }}" class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Fungua Profile</a>@endif
            <x-secondary-button wire:click="$set('generatedPassword', null)">Funga</x-secondary-button>
        </div>
    </x-modal>
</div>
