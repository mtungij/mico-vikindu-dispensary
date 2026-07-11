<div class="space-y-6">
    <div class="flex flex-wrap gap-2">
        @foreach (['basic' => 'Taarifa za Kituo', 'address' => 'Mahali', 'legal' => 'Leseni na NHIF', 'branding' => 'Branding', 'documents' => 'Nyaraka', 'preferences' => 'Preferences', 'reception_billing' => 'Reception Billing'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="rounded-md px-3 py-2 text-sm font-medium {{ $tab === $key ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-card-dark dark:text-slate-300 dark:hover:bg-slate-800' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'basic')
        <x-card>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Taarifa za Kituo</h2>
                <x-secondary-button wire:click="editBasic"><x-lucide-pencil class="h-4 w-4" /> Badilisha</x-secondary-button>
            </div>
            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Jina</dt><dd class="font-medium">{{ $facility->name }}</dd></div>
                <div><dt class="text-sm text-slate-500">Code</dt><dd class="font-medium">{{ $facility->code }}</dd></div>
                <div><dt class="text-sm text-slate-500">Simu</dt><dd class="font-medium">{{ $facility->phone_primary }}</dd></div>
                <div><dt class="text-sm text-slate-500">Email</dt><dd class="font-medium">{{ $facility->email ?? '-' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Aina</dt><dd class="font-medium">{{ $facility->facility_type->label() }}</dd></div>
                <div><dt class="text-sm text-slate-500">Umiliki</dt><dd class="font-medium">{{ $facility->ownership_type->label() }}</dd></div>
            </dl>
        </x-card>
    @endif

    @if ($tab === 'address')
        <x-card>
            <h2 class="font-semibold">Mahali</h2>
            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Mkoa</dt><dd>{{ $facility->region }}</dd></div>
                <div><dt class="text-sm text-slate-500">Wilaya</dt><dd>{{ $facility->district }}</dd></div>
                <div><dt class="text-sm text-slate-500">Kata</dt><dd>{{ $facility->ward }}</dd></div>
                <div><dt class="text-sm text-slate-500">Physical address</dt><dd>{{ $facility->physical_address }}</dd></div>
            </dl>
        </x-card>
    @endif

    @if ($tab === 'legal')
        <x-card>
            <h2 class="font-semibold">Leseni na NHIF</h2>
            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Registration</dt><dd>{{ $facility->registration_number ?? '-' }}</dd></div>
                <div><dt class="text-sm text-slate-500">TIN</dt><dd>{{ $facility->tin_number ?? '-' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Operating license</dt><dd>{{ $facility->operating_license_number ?? '-' }}</dd></div>
                <div><dt class="text-sm text-slate-500">NHIF</dt><dd>{{ $facility->nhif_accreditation_number ?? '-' }}</dd></div>
            </dl>
        </x-card>
    @endif

    @if ($tab === 'branding')
        <x-card>
            <form wire:submit="updateBranding" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-file-upload label="Logo" model="logo" accept="image/jpeg,image/png,image/webp" />
                    <x-file-upload label="Official stamp" model="official_stamp" accept="image/jpeg,image/png,image/webp" />
                    <x-image-preview :path="$facility->logo_path" label="Logo" />
                    <x-image-preview :path="$facility->official_stamp_path" label="Official stamp" />
                </div>
                <x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi Branding</x-primary-button>
            </form>
        </x-card>
    @endif

    @if ($tab === 'documents')
        <x-card>
            <h2 class="font-semibold">Nyaraka</h2>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left dark:bg-slate-800"><tr><th class="p-3">Type</th><th class="p-3">Name</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($facility->documents as $document)
                            <tr class="border-t border-slate-200 dark:border-slate-700">
                                <td class="p-3">{{ $document->document_type->label() }}</td>
                                <td class="p-3">{{ $document->document_name }}</td>
                                <td class="p-3"><x-badge tone="warning">{{ $document->verification_status->label() }}</x-badge></td>
                                <td class="p-3">
                                    <div class="flex gap-1">
                                        <a href="{{ route('settings.facility.documents.view', $document) }}" target="_blank" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-eye class="h-4 w-4" /></a>
                                        <a href="{{ route('settings.facility.documents.download', $document) }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-download class="h-4 w-4" /></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4"><x-empty-state icon="file-text" title="Hakuna nyaraka" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if ($tab === 'preferences')
        <x-card>
            <h2 class="font-semibold">Preferences</h2>
            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Currency</dt><dd>{{ $facility->currency }} ({{ $facility->currency_symbol }})</dd></div>
                <div><dt class="text-sm text-slate-500">Timezone</dt><dd>{{ $facility->timezone }}</dd></div>
                <div><dt class="text-sm text-slate-500">Primary color</dt><dd class="font-mono">{{ $facility->primary_color }}</dd></div>
                <div><dt class="text-sm text-slate-500">Receipt footer</dt><dd>{{ $facility->receipt_footer ?? '-' }}</dd></div>
            </dl>
        </x-card>
    @endif

    @if ($tab === 'reception_billing')
        <x-card>
            <form wire:submit="saveReceptionBilling" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><x-checkbox wire:model.live="auto_add_registration_fee" /> Auto add registration fee</label>
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><x-checkbox wire:model.live="auto_add_consultation_fee" /> Auto add consultation fee</label>
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><x-checkbox wire:model.live="charge_new_patient_registration" /> Charge new patient registration</label>
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><x-checkbox wire:model.live="charge_returning_patient_registration" /> Charge returning patient registration</label>
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"><x-checkbox wire:model.live="require_consultation_service" /> Require consultation service when destination requires consultation</label>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div><x-input-label value="New patient registration service" /><x-select-input wire:model="new_patient_registration_service_id" class="mt-1"><option value="">Chagua</option>@foreach($registrationServices as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</x-select-input><x-input-error :messages="$errors->get('new_patient_registration_service_id')" /></div>
                    <div><x-input-label value="Returning patient registration service" /><x-select-input wire:model="returning_patient_registration_service_id" class="mt-1"><option value="">Chagua</option>@foreach($registrationServices as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</x-select-input><x-input-error :messages="$errors->get('returning_patient_registration_service_id')" /></div>
                    <div><x-input-label value="Patient card replacement service" /><x-select-input wire:model="patient_card_replacement_service_id" class="mt-1"><option value="">Chagua</option>@foreach($administrativeServices as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</x-select-input><x-input-error :messages="$errors->get('patient_card_replacement_service_id')" /></div>
                </div>
                <div class="flex justify-end"><x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi Reception Billing</x-primary-button></div>
            </form>
        </x-card>
    @endif

    <x-modal :show="$showBasicModal" title="Badilisha Taarifa za Kituo" max-width="2xl" close="$set('showBasicModal', false)">
        <form wire:submit="saveBasic" class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><x-input-label value="Jina" /><x-text-input wire:model.live="name" class="mt-1" /><x-input-error :messages="$errors->get('name')" /></div>
            <div><x-input-label value="Code" /><x-text-input wire:model.live="code" class="mt-1" /><x-input-error :messages="$errors->get('code')" /></div>
            <div><x-input-label value="Primary phone" /><x-text-input wire:model.live="phone_primary" class="mt-1" /><x-input-error :messages="$errors->get('phone_primary')" /></div>
            <div><x-input-label value="Email" /><x-text-input wire:model.live="email" type="email" class="mt-1" /></div>
            <div><x-input-label value="Registration number" /><x-text-input wire:model.live="registration_number" class="mt-1" /></div>
            <div><x-input-label value="TIN" /><x-text-input wire:model.live="tin_number" class="mt-1" /></div>
            <div><x-input-label value="NHIF accreditation" /><x-text-input wire:model.live="nhif_accreditation_number" class="mt-1" /></div>
            <div class="md:col-span-2"><x-input-label value="Receipt footer" /><x-textarea wire:model.live="receipt_footer" rows="3" class="mt-1" /></div>
            <div class="md:col-span-2 flex justify-end gap-2"><x-secondary-button wire:click="$set('showBasicModal', false)">Ghairi</x-secondary-button><x-primary-button>Hifadhi</x-primary-button></div>
        </form>
    </x-modal>
</div>
