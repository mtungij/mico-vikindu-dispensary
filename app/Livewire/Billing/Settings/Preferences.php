<?php

namespace App\Livewire\Billing\Settings;

use App\Models\FacilitySetting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Masmerise\Toaster\Toaster as Notifier;

class Preferences extends Component
{
    public array $settings = [];
    public string $section = 'preferences';

    public function mount(string $section = 'preferences'): void
    {
        Gate::authorize('billing.manage-settings');
        $this->section = $section;

        foreach ($this->settingKeys() as $key) {
            $this->settings[$key] = FacilitySetting::query()
                ->where('facility_id', currentFacility()?->id)
                ->where('key', $key)
                ->value('value') ?? '';
        }
    }

    public function save(): void
    {
        Gate::authorize('billing.manage-settings');

        foreach ($this->settings as $key => $value) {
            FacilitySetting::query()->updateOrCreate(
                ['facility_id' => currentFacility()?->id, 'key' => $key],
                ['value' => (string) $value, 'type' => 'string', 'group' => 'billing', 'is_public' => false],
            );
        }

        Notifier::success('messages.saved');
    }

    public function render()
    {
        return view('livewire.billing.settings.preferences')->layout('components.layouts.app', [
            'title' => 'Billing Preferences',
            'description' => 'Payment rules, receipt settings and cashier settings.',
        ]);
    }

    private function settingKeys(): array
    {
        return [
            'billing_allow_partial_payment',
            'billing_partial_payment_can_release_patient',
            'billing_release_mode',
            'billing_allow_overpayment',
            'billing_auto_print_receipt',
            'billing_require_discount_approval',
            'billing_require_waiver_approval',
            'billing_require_refund_approval',
            'billing_require_reversal_approval',
        ];
    }
}
