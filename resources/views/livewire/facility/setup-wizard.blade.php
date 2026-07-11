<div class="mx-auto max-w-6xl space-y-6" x-data x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    @php
        $steps = [
            1 => 'Msingi',
            2 => 'Mahali',
            3 => 'Leseni',
            4 => 'Branding',
            5 => 'Preferences',
            6 => 'Hakiki',
        ];
    @endphp

    <x-setup-stepper :steps="$steps" :current="$step" :progress="$progress['percentage'] ?? 0" />

    <x-card>
        <div class="mb-6 flex items-start gap-3">
            <div class="rounded-lg bg-primary/10 p-3 text-primary">
                <x-dynamic-component :component="match($step) { 1 => 'lucide-building-2', 2 => 'lucide-map-pin', 3 => 'lucide-shield-check', 4 => 'lucide-palette', 5 => 'lucide-settings', default => 'lucide-check-circle-2' }" class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-lg font-semibold">Step {{ $step }}: {{ $steps[$step] }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Hifadhi draft baada ya kila hatua. Data isiyo valid haitahifadhiwa.</p>
            </div>
        </div>

        @if ($step === 1)
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-input-label value="Jina la kituo" />
                    <x-text-input wire:model.live.debounce.400ms="name" class="mt-1" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label value="Facility code" />
                    <x-text-input wire:model.live="code" class="mt-1 uppercase" />
                    <x-input-error :messages="$errors->get('code')" />
                </div>
                <div>
                    <x-input-label value="Aina ya kituo" />
                    <x-select-input wire:model.live="facility_type" class="mt-1">
                        <option value="">Chagua</option>
                        @foreach ($facilityTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('facility_type')" />
                </div>
                <div>
                    <x-input-label value="Aina ya umiliki" />
                    <x-select-input wire:model.live="ownership_type" class="mt-1">
                        <option value="">Chagua</option>
                        @foreach ($ownershipTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('ownership_type')" />
                </div>
                <div><x-input-label value="Namba ya usajili" /><x-text-input wire:model.live="registration_number" class="mt-1" /><x-input-error :messages="$errors->get('registration_number')" /></div>
                <div><x-input-label value="TIN number" /><x-text-input wire:model.live="tin_number" class="mt-1" /><x-input-error :messages="$errors->get('tin_number')" /></div>
                <div><x-input-label value="Primary phone" /><x-text-input wire:model.live="phone_primary" class="mt-1" placeholder="0712345678" /><x-input-error :messages="$errors->get('phone_primary')" /></div>
                <div><x-input-label value="Secondary phone" /><x-text-input wire:model.live="phone_secondary" class="mt-1" /><x-input-error :messages="$errors->get('phone_secondary')" /></div>
                <div><x-input-label value="Email" /><x-text-input wire:model.live="email" type="email" class="mt-1" /><x-input-error :messages="$errors->get('email')" /></div>
                <div><x-input-label value="Website" /><x-text-input wire:model.live="website" type="url" class="mt-1" placeholder="https://example.com" /><x-input-error :messages="$errors->get('website')" /></div>
            </div>
        @endif

        @if ($step === 2)
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Nchi" /><x-text-input wire:model.live="country" class="mt-1" /><x-input-error :messages="$errors->get('country')" /></div>
                <div><x-input-label value="Timezone" /><x-text-input wire:model.live="timezone" class="mt-1" /><x-input-error :messages="$errors->get('timezone')" /></div>
                <div>
                    <x-input-label value="Mkoa" />
                    <x-select-input wire:model.live="region" class="mt-1">
                        <option value="">Chagua mkoa</option>
                        @foreach ($regions as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('region')" />
                </div>
                <div>
                    <x-input-label value="Wilaya" />
                    <x-select-input wire:model.live="district" class="mt-1">
                        <option value="">Chagua wilaya</option>
                        @foreach ($this->districts() as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('district')" />
                </div>
                <div><x-input-label value="Council/Halmashauri" /><x-text-input wire:model.live="council" class="mt-1" /></div>
                <div><x-input-label value="Kata" /><x-text-input wire:model.live="ward" class="mt-1" /><x-input-error :messages="$errors->get('ward')" /></div>
                <div><x-input-label value="Mtaa/Kijiji" /><x-text-input wire:model.live="street_or_village" class="mt-1" /></div>
                <div><x-input-label value="Postal address" /><x-text-input wire:model.live="postal_address" class="mt-1" /></div>
                <div class="md:col-span-2"><x-input-label value="Physical address" /><x-textarea wire:model.live="physical_address" class="mt-1" rows="3" /><x-input-error :messages="$errors->get('physical_address')" /></div>
            </div>
        @endif

        @if ($step === 3)
            <div class="mb-4 rounded-md bg-blue-50 p-4 text-sm text-info dark:bg-blue-950/30">Taarifa hizi zitatumika kwenye NHIF Claim Report na nyaraka za bima.</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Operating license number" /><x-text-input wire:model.live="operating_license_number" class="mt-1" /></div>
                <div><x-input-label value="Operating license expiry date" /><x-text-input wire:model.live="operating_license_expiry_date" type="date" class="mt-1" /><x-input-error :messages="$errors->get('operating_license_expiry_date')" /></div>
                <div class="md:col-span-2">
                    <x-settings-toggle label="Je, kituo hiki kinapokea wagonjwa wa bima?" model="accepts_insurance" />
                </div>
                @if ($accepts_insurance)
                    <x-settings-toggle label="NHIF enabled" model="nhif_enabled" />
                    <x-settings-toggle label="Other insurance enabled" model="other_insurance_enabled" />
                    <div><x-input-label value="NHIF accreditation number" /><x-text-input wire:model.live="nhif_accreditation_number" class="mt-1" /><x-input-error :messages="$errors->get('nhif_accreditation_number')" /></div>
                    <div><x-input-label value="NHIF contract number" /><x-text-input wire:model.live="nhif_contract_number" class="mt-1" /></div>
                @endif
                @if ($operating_license_expiry_date && now()->toDateString() > $operating_license_expiry_date)
                    <div class="md:col-span-2 rounded-md bg-amber-50 p-4 text-sm text-warning dark:bg-amber-950/30">
                        <label class="flex items-start gap-2"><x-checkbox wire:model.live="license_expired_acknowledged" /> <span>Leseni inaonekana imeisha muda. Nakubali kuendelea na warning status.</span></label>
                    </div>
                @endif
            </div>
        @endif

        @if ($step === 4)
            <div class="grid gap-4 lg:grid-cols-3">
                <x-file-upload label="Facility logo" model="logo" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, WEBP. Max 2MB." />
                <x-file-upload label="Favicon" model="favicon" accept="image/png,image/jpeg,image/x-icon" hint="PNG, ICO, JPG. Max 1MB." />
                <x-file-upload label="Official stamp" model="official_stamp" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, WEBP. Max 2MB." />
                <x-image-preview :path="$facility?->logo_path" label="Logo iliyopo" />
                <x-image-preview :path="$facility?->favicon_path" label="Favicon iliyopo" />
                <x-image-preview :path="$facility?->official_stamp_path" label="Stamp iliyopo" />
            </div>
            <div class="mt-6 flex items-center justify-between">
                <h3 class="font-semibold">Nyaraka zilizopakiwa</h3>
                <x-primary-button type="button" wire:click="$set('showDocumentModal', true)"><x-lucide-plus class="h-4 w-4" /> Ongeza Nyaraka Nyingine</x-primary-button>
            </div>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left dark:bg-slate-800"><tr><th class="p-3">Type</th><th class="p-3">Name</th><th class="p-3">Number</th><th class="p-3">Expiry</th><th class="p-3">Status</th><th class="p-3">Uploaded by</th><th class="p-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($facility?->documents ?? [] as $document)
                            <tr wire:key="document-{{ $document->id }}">
                                <td class="p-3">{{ $document->document_type->label() }}</td>
                                <td class="p-3">{{ $document->document_name }}</td>
                                <td class="p-3">{{ $document->document_number ?? '-' }}</td>
                                <td class="p-3">{{ $document->expiry_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3"><x-badge tone="warning">{{ $document->verification_status->label() }}</x-badge></td>
                                <td class="p-3">{{ $document->uploader?->name }}</td>
                                <td class="p-3 text-right"><x-icon-button wire:click="deleteDocument({{ $document->id }})"><x-lucide-trash-2 class="h-4 w-4" /></x-icon-button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-4"><x-empty-state icon="file-text" title="Hakuna nyaraka" message="Nyaraka za kituo zitaonekana hapa." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($step === 5)
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><x-input-label value="Default language" /><x-select-input wire:model.live="default_language" class="mt-1"><option value="sw">Kiswahili</option><option value="en">English</option></x-select-input></div>
                        <div><x-input-label value="Fallback language" /><x-select-input wire:model.live="fallback_language" class="mt-1"><option value="en">English</option><option value="sw">Kiswahili</option></x-select-input></div>
                        <div><x-input-label value="Currency" /><x-text-input wire:model.live="currency" class="mt-1" /></div>
                        <div><x-input-label value="Currency symbol" /><x-text-input wire:model.live="currency_symbol" class="mt-1" /></div>
                        <div><x-input-label value="Date format" /><x-text-input wire:model.live="date_format" class="mt-1" /></div>
                        <div><x-input-label value="Time format" /><x-text-input wire:model.live="time_format" class="mt-1" /></div>
                    </div>
                    <x-color-picker label="Primary color" model="primary_color" />
                    <x-color-picker label="Secondary color" model="secondary_color" />
                    <x-textarea wire:model.live="receipt_header" rows="2" placeholder="Receipt header" />
                    <x-textarea wire:model.live="receipt_footer" rows="2" placeholder="Receipt footer" />
                    <x-textarea wire:model.live="report_footer" rows="2" placeholder="Report footer" />
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach (['enable_patient_numbers' => 'Patient numbers', 'enable_receipt_numbers' => 'Receipt numbers', 'enable_invoice_numbers' => 'Invoice numbers', 'require_payment_before_service' => 'Payment before service', 'allow_partial_payments' => 'Partial payments', 'enable_audit_logs' => 'Audit logs', 'enable_file_attachments' => 'File attachments', 'enable_sms_notifications' => 'SMS notifications', 'enable_email_notifications' => 'Email notifications', 'enable_whatsapp_notifications' => 'WhatsApp notifications'] as $model => $label)
                            <x-settings-toggle :label="$label" :model="$model" />
                        @endforeach
                    </div>
                </div>
                <div class="space-y-4">
                    <x-card style="border-top: 5px solid {{ $primary_color }}">
                        <p class="text-xs text-slate-500">Receipt preview</p>
                        <h3 class="mt-2 font-semibold">{{ $name ?: 'Facility name' }}</h3>
                        <p class="text-sm text-slate-500">{{ $physical_address ?: 'Address' }} | {{ $phone_primary ?: 'Phone' }}</p>
                        <div class="my-4 rounded-md bg-slate-50 p-3 text-sm dark:bg-slate-800">{{ $receipt_header ?: 'Receipt header' }}</div>
                        <p class="text-xs text-slate-500">{{ $receipt_footer ?: 'Receipt footer' }}</p>
                    </x-card>
                    <x-card>
                        <p class="text-xs text-slate-500">Report preview</p>
                        <div class="mt-2 flex items-center gap-3"><x-facility-logo :facility="$facility" /><div><h3 class="font-semibold">{{ $name ?: 'Facility name' }}</h3><p class="text-sm text-slate-500">{{ $report_footer ?: 'Report footer' }}</p></div></div>
                    </x-card>
                </div>
            </div>
        @endif

        @if ($step === 6)
            @php($readiness = $this->readiness())
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach (['Basic Information' => 1, 'Address' => 2, 'Legal and NHIF' => 3, 'Branding' => 4, 'Preferences' => 5] as $section => $targetStep)
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between"><h3 class="font-semibold">{{ $section }}</h3><button type="button" wire:click="goToStep({{ $targetStep }})" class="text-sm text-primary">Badilisha</button></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <x-card>
                    <h3 class="font-semibold">Readiness checklist</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($readiness['blocking'] as $item)
                            <p class="flex items-center gap-2 text-sm text-danger"><x-lucide-triangle-alert class="h-4 w-4" /> {{ $item }}</p>
                        @empty
                            <p class="flex items-center gap-2 text-sm text-success"><x-lucide-check-circle-2 class="h-4 w-4" /> Hakuna blocking errors.</p>
                        @endforelse
                    </div>
                    <x-input-error :messages="$errors->get('completion')" />
                </x-card>
                <x-card>
                    <h3 class="font-semibold">Warnings</h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($readiness['warnings'] as $item)
                            <p class="flex items-center gap-2 text-sm text-warning"><x-lucide-triangle-alert class="h-4 w-4" /> {{ $item }}</p>
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endif
    </x-card>

    <div class="sticky bottom-0 z-20 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-card-dark">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <x-secondary-button wire:click="previousStep" :disabled="$step === 1"><x-lucide-chevron-left class="h-4 w-4" /> Rudi</x-secondary-button>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-secondary-button wire:click="saveDraft" wire:loading.attr="disabled"><x-lucide-save class="h-4 w-4" /> Hifadhi Rasimu</x-secondary-button>
                @if ($step < 6)
                    <x-primary-button type="button" wire:click="nextStep" wire:loading.attr="disabled">Endelea <x-lucide-chevron-right class="h-4 w-4" /></x-primary-button>
                @else
                    <x-primary-button type="button" wire:click="completeSetup" wire:loading.attr="disabled"><x-lucide-check-circle-2 class="h-4 w-4" /> Kamilisha Setup</x-primary-button>
                @endif
            </div>
        </div>
    </div>

    <x-modal :show="$showDocumentModal" title="Ongeza Nyaraka Nyingine" max-width="2xl" close="$set('showDocumentModal', false)">
        <form wire:submit="uploadDocument" class="grid gap-4 md:grid-cols-2">
            <div><x-input-label value="Document type" /><x-select-input wire:model.live="document_type" class="mt-1">@foreach ($documentTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</x-select-input></div>
            <div><x-input-label value="Document name" /><x-text-input wire:model.live="document_name" class="mt-1" /><x-input-error :messages="$errors->get('document_name')" /></div>
            <div><x-input-label value="Document number" /><x-text-input wire:model.live="document_number" class="mt-1" /></div>
            <div><x-input-label value="Issue date" /><x-text-input wire:model.live="document_issue_date" type="date" class="mt-1" /></div>
            <div><x-input-label value="Expiry date" /><x-text-input wire:model.live="document_expiry_date" type="date" class="mt-1" /></div>
            <div><x-input-label value="File" /><input type="file" wire:model="document_file" class="mt-1 block w-full text-sm"><x-input-error :messages="$errors->get('document_file')" /></div>
            <div class="md:col-span-2"><x-input-label value="Notes" /><x-textarea wire:model.live="document_notes" rows="3" class="mt-1" /></div>
            <div class="md:col-span-2 flex justify-end gap-2"><x-secondary-button wire:click="$set('showDocumentModal', false)">Ghairi</x-secondary-button><x-primary-button><x-lucide-upload class="h-4 w-4" /> Hifadhi Nyaraka</x-primary-button></div>
        </form>
    </x-modal>
</div>
