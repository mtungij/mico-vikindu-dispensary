<div class="space-y-6">
    <x-card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-md bg-primary text-xl font-semibold text-white">{{ $staffProfile->initials() }}</div>
                <div>
                    <h2 class="text-xl font-semibold">{{ $staffProfile->fullName() }}</h2>
                    <p class="text-sm text-slate-500">{{ $staffProfile->employee_number }} - {{ $staffProfile->user->email }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-badge :tone="$staffProfile->employmentRecord?->employment_status?->badge() ?? 'warning'">{{ $staffProfile->employmentRecord?->employment_status?->label() ?? '-' }}</x-badge>
                        <x-badge :tone="$staffProfile->user->status->badge()">{{ $staffProfile->user->status->label() }}</x-badge>
                        @foreach($staffProfile->user->roles as $role)<x-badge tone="info">{{ $role->display_name ?? $role->name }}</x-badge>@endforeach
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('activate', $staffProfile)<x-secondary-button wire:click="activate"><x-lucide-circle-check class="h-4 w-4" /> Activate</x-secondary-button>@endcan
                @can('suspend', $staffProfile)<x-secondary-button wire:click="suspend" wire:confirm="Simamisha account hii?"><x-lucide-ban class="h-4 w-4" /> Suspend</x-secondary-button>@endcan
                @can('resetPassword', $staffProfile)<x-secondary-button wire:click="resetPassword"><x-lucide-key-round class="h-4 w-4" /> Reset Password</x-secondary-button>@endcan
                @can('manageDocuments', $staffProfile)<x-primary-button wire:click="openDocumentModal"><x-lucide-upload class="h-4 w-4" /> Upload Document</x-primary-button>@endcan
            </div>
        </div>
    </x-card>

    <div class="grid gap-4 md:grid-cols-4">
        <x-card><p class="text-sm text-slate-500">Completion</p><p class="mt-2 text-2xl font-semibold">{{ $completion }}%</p></x-card>
        <x-card><p class="text-sm text-slate-500">Department</p><p class="mt-2 font-semibold">{{ $staffProfile->employmentRecord?->primaryDepartment?->name ?? '-' }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Job Title</p><p class="mt-2 font-semibold">{{ $staffProfile->employmentRecord?->jobTitle?->name ?? '-' }}</p></x-card>
        <x-card><p class="text-sm text-slate-500">Last Login</p><p class="mt-2 font-semibold">{{ $staffProfile->user->last_login_at?->diffForHumans() ?? '-' }}</p></x-card>
    </div>

    @if($warnings)
        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
            @foreach($warnings as $warning)<p>{{ $warning }}</p>@endforeach
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach(['summary' => 'Muhtasari', 'personal' => 'Binafsi', 'employment' => 'Ajira', 'roles' => 'Roles', 'education' => 'Elimu', 'licenses' => 'Leseni', 'documents' => 'Nyaraka', 'signature' => 'Signature', 'contacts' => 'Emergency', 'login' => 'Login History', 'activity' => 'Activity'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $label }}</button>
        @endforeach
    </div>

    <x-card>
        @if($tab === 'summary')
            <div class="grid gap-4 md:grid-cols-2">
                <p><span class="text-slate-500">Phone:</span> {{ $staffProfile->primary_phone }}</p>
                <p><span class="text-slate-500">Personal email:</span> {{ $staffProfile->personal_email ?? '-' }}</p>
                <p><span class="text-slate-500">Highest education:</span> {{ $staffProfile->highestQualification()?->course_name ?? '-' }}</p>
                <p><span class="text-slate-500">Documents:</span> {{ $staffProfile->documents->count() }}</p>
            </div>
        @elseif($tab === 'personal')
            <div class="grid gap-3 md:grid-cols-2">
                <p>NIDA: {{ $staffProfile->nida_number ?? '-' }}</p>
                <p>Passport: {{ $staffProfile->passport_number ?? '-' }}</p>
                <p>Address: {{ $staffProfile->physical_address ?? '-' }}</p>
                <p>Age: {{ $staffProfile->currentAge() ?? '-' }}</p>
            </div>
        @elseif($tab === 'employment')
            <div class="grid gap-3 md:grid-cols-2">
                <p>Status: {{ $staffProfile->employmentRecord?->employment_status?->label() ?? '-' }}</p>
                <p>Category: {{ $staffProfile->employmentRecord?->employment_category?->label() ?? '-' }}</p>
                <p>Start: {{ $staffProfile->employmentRecord?->employment_start_date?->format('d/m/Y') ?? '-' }}</p>
                <p>Work location: {{ $staffProfile->employmentRecord?->work_location ?? '-' }}</p>
            </div>
        @elseif($tab === 'roles')
            <h3 class="font-semibold">Effective Permissions</h3>
            <div class="mt-3 flex flex-wrap gap-2">@foreach($staffProfile->user->getAllPermissions() as $permission)<x-badge>{{ $permission->name }}</x-badge>@endforeach</div>
        @elseif($tab === 'education')
            <div class="space-y-3">@foreach($staffProfile->educationRecords as $record)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-semibold">{{ $record->course_name }}</p><p class="text-sm text-slate-500">{{ $record->institution_name }} - {{ $record->education_level?->label() }}</p></div>@endforeach</div>
        @elseif($tab === 'licenses')
            <div class="space-y-3">@foreach($staffProfile->professionalLicenses as $record)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-semibold">{{ $record->license_type }}</p><p class="text-sm text-slate-500">{{ $record->professional_body }} - {{ $record->registration_number }}</p><x-badge :tone="$record->status->badge()">{{ $record->status->label() }}</x-badge></div>@endforeach</div>
        @elseif($tab === 'documents')
            <div class="space-y-3">@foreach($staffProfile->documents as $document)<div class="flex items-center justify-between rounded-md border border-slate-200 p-3 dark:border-slate-700"><div><p class="font-semibold">{{ $document->document_name }}</p><p class="text-sm text-slate-500">{{ $document->document_type?->label() }}</p></div><div class="flex gap-2"><a class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('staff.documents.view', [$staffProfile, $document]) }}" target="_blank"><x-lucide-eye class="h-4 w-4" /></a><a class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('staff.documents.download', [$staffProfile, $document]) }}"><x-lucide-download class="h-4 w-4" /></a></div></div>@endforeach</div>
        @elseif($tab === 'signature')
            <div class="space-y-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold">Current Signature Preview</h3>
                        <p class="text-sm text-slate-500">PNG yenye transparent background inapendekezwa. JPG/JPEG pia zinaruhusiwa.</p>
                    </div>
                    @can('manageSignature', $staffProfile)
                        <div class="flex flex-wrap gap-2">
                            <x-primary-button wire:click="openSignatureModal"><x-lucide-upload class="h-4 w-4" /> {{ $staffProfile->activeSignature ? 'Replace Signature' : 'Upload Signature' }}</x-primary-button>
                            @if($staffProfile->activeSignature)
                                <x-secondary-button wire:click="deleteSignature" wire:confirm="Futa signature hii?"><x-lucide-trash-2 class="h-4 w-4" /> Delete Signature</x-secondary-button>
                            @endif
                        </div>
                    @endcan
                </div>
                @if($staffProfile->activeSignature)
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
                        <img src="{{ route('staff.signatures.view', [$staffProfile, $staffProfile->activeSignature]) }}" alt="Digital signature ya {{ $staffProfile->fullName() }}" class="max-h-36 max-w-full rounded-md bg-white p-3 dark:bg-white">
                        <p class="mt-3 text-xs text-slate-500">Uploaded {{ $staffProfile->activeSignature->uploaded_at?->format('d/m/Y H:i') }}</p>
                    </div>
                @else
                    <div class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">
                        Hakuna signature iliyowekwa.
                    </div>
                @endif
            </div>
        @elseif($tab === 'contacts')
            <div class="space-y-3">@foreach($staffProfile->emergencyContacts as $contact)<div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-semibold">{{ $contact->full_name }}</p><p class="text-sm text-slate-500">{{ $contact->relationship }} - {{ $contact->primary_phone }}</p></div>@endforeach</div>
        @elseif($tab === 'login')
            <div class="space-y-2">@foreach($loginHistories as $history)<p class="text-sm">{{ $history->created_at?->format('d/m/Y H:i') }} - {{ $history->status }} - {{ $history->ip_address }}</p>@endforeach</div>
        @elseif($tab === 'activity')
            <div class="space-y-2">@foreach($activities as $activity)<p class="text-sm">{{ $activity->created_at?->format('d/m/Y H:i') }} - {{ $activity->event }}</p>@endforeach</div>
        @endif
    </x-card>

    <x-modal :show="$showDocumentModal" title="Upload Document" close="$set('showDocumentModal', false)" maxWidth="2xl">
        <form wire:submit="uploadDocument" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Type" /><x-select-input wire:model="documentForm.document_type">@foreach($documentTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</x-select-input></div>
                <div><x-input-label value="Name" /><x-text-input wire:model="documentForm.document_name" /></div>
                <div><x-input-label value="Number" /><x-text-input wire:model="documentForm.document_number" /></div>
                <div><x-input-label value="Expiry" /><x-text-input type="date" wire:model="documentForm.expiry_date" /></div>
            </div>
            <x-file-upload label="File" model="documentFile" accept="application/pdf,image/png,image/jpeg,image/webp" hint="PDF, JPG, PNG au WEBP, max 5MB" />
            <div class="flex justify-end"><x-primary-button><x-lucide-upload class="h-4 w-4" /> Upload</x-primary-button></div>
        </form>
    </x-modal>

    <x-modal :show="$showPasswordModal" title="Temporary Password" maxWidth="md">
        <p class="text-sm text-slate-600 dark:text-slate-300">Nenosiri hili linaonekana mara moja tu.</p>
        <div class="mt-3 rounded-md bg-slate-100 p-3 font-mono text-sm dark:bg-slate-800">{{ $temporaryPassword }}</div>
        <div class="mt-4 flex justify-end"><x-secondary-button wire:click="$set('showPasswordModal', false)">Funga</x-secondary-button></div>
    </x-modal>

    <x-modal :show="$showSignatureModal" title="Upload Signature" close="$set('showSignatureModal', false)" maxWidth="lg">
        <form wire:submit="uploadSignature" class="space-y-4">
            <x-file-upload label="Signature" model="signatureFile" accept="image/png,image/jpeg" hint="PNG, JPG au JPEG, max 1MB. PNG transparent background inapendekezwa." />
            @if($signatureFile)
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="mb-2 text-sm font-semibold">Preview kabla ya kuhifadhi</p>
                    <img src="{{ $signatureFile->temporaryUrl() }}" alt="Signature preview" class="max-h-32 max-w-full rounded-md bg-white p-3">
                </div>
            @endif
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" wire:click="$set('showSignatureModal', false)">Funga</x-secondary-button>
                <x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi Signature</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
