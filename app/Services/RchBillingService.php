<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Service;

class RchBillingService
{
    public function __construct(private readonly BillingChargeService $charges) {}
    public function addAncCharge(Invoice $invoice, Service $service, $actor, ?object $source = null) { return $this->charges->addServiceCharge($invoice, $service, $actor, $source, 1, ['program' => 'anc']); }
    public function addFamilyPlanningCharge(Invoice $invoice, Service $service, $actor, ?object $source = null) { return $this->charges->addServiceCharge($invoice, $service, $actor, $source, 1, ['program' => 'family_planning']); }
    public function addGrowthMonitoringCharge(Invoice $invoice, Service $service, $actor, ?object $source = null) { return $this->charges->addServiceCharge($invoice, $service, $actor, $source, 1, ['program' => 'child_growth']); }
    public function addNutritionCharge(Invoice $invoice, Service $service, $actor, ?object $source = null) { return $this->charges->addServiceCharge($invoice, $service, $actor, $source, 1, ['program' => 'nutrition']); }
    public function addImmunizationCharge(Invoice $invoice, Service $service, $actor, ?object $source = null) { return $this->charges->addServiceCharge($invoice, $service, $actor, $source, 1, ['program' => 'immunization']); }
    public function calculatePayerSplit(Invoice $invoice): array { return ['payer_type' => $invoice->payer_type?->value ?? $invoice->payer_type, 'balance' => (float) $invoice->balance_amount]; }
    public function validatePayment(Invoice $invoice): bool { return (float) $invoice->balance_amount <= 0; }
    public function preventDuplicateCharge(Invoice $invoice, object $source): bool { return $this->charges->preventDuplicateCharge($invoice, $source); }
}
