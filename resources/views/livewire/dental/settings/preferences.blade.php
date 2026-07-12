<x-card>
    <form wire:submit="save" class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2">
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_require_payment_before_consultation" /> Payment before consultation</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_require_payment_before_procedure" /> Payment before procedure</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_allow_emergency_override" /> Allow emergency override</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_bill_materials_separately" /> Bill materials separately</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_require_consent_for_surgery" /> Require consent for surgery</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_require_signature_for_report" /> Require signature for report</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_enable_periodontal_charting" /> Periodontal charting</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_enable_mixed_dentition" /> Mixed dentition</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_enable_chair_assignment" /> Chair assignment</label>
            <label class="flex gap-2 text-sm"><x-checkbox wire:model="dental_auto_create_follow_up" /> Auto follow-up foundation</label>
            <div>
                <x-input-label value="Numbering" />
                <x-select-input wire:model="dental_default_numbering_system">
                    <option value="fdi">FDI</option><option value="universal">Universal</option><option value="palmer">Palmer</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Attachment max MB" />
                <x-text-input wire:model="dental_attachment_max_mb" type="number" min="1" max="50" />
            </div>
        </div>
        <x-primary-button>Hifadhi</x-primary-button>
    </form>
</x-card>
