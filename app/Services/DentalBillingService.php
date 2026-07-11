<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Models\DentalProcedure;
use App\Models\DentalTreatmentPlanItem;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

class DentalBillingService
{
    public function __construct(private readonly InvoiceService $invoices, private readonly ServicePricingService $pricing) {}
    public function resolveConsultationPrice(Service $service, Visit $visit) { return $this->price($service, $visit); }
    public function resolveProcedurePrice(Service $service, Visit $visit) { return $this->price($service, $visit); }
    public function addTreatmentPlanItemCharge(DentalTreatmentPlanItem $item, $actor): ?\App\Models\InvoiceItem
    {
        $plan = $item->plan()->with('visit.invoice')->first();
        $invoice = $plan->visit->invoice ?: $this->invoices->createVisitInvoice($plan->visit, [], $actor);
        if ($item->invoice_item_id) return null;
        $created = $this->addServiceCharge($invoice, $item->service, $actor, ['charge_source'=>'dental_treatment_plan','treatment_plan_item_id'=>$item->id], $item->quantity);
        $item->update(['invoice_item_id'=>$created->id]);
        return $created;
    }
    public function addProcedureCharge(DentalProcedure $procedure, $actor): ?\App\Models\InvoiceItem
    {
        if (! $procedure->service || $procedure->invoice_item_id) return null;
        $invoice = $procedure->visit->invoice ?: $this->invoices->createVisitInvoice($procedure->visit, [], $actor);
        $item = $this->addServiceCharge($invoice, $procedure->service, $actor, ['charge_source'=>'dental_procedure','dental_procedure_id'=>$procedure->id,'tooth_number'=>$procedure->tooth_number]);
        $procedure->update(['invoice_item_id'=>$item->id]);
        return $item;
    }
    public function addMaterialChargeIfBillable(): void {}
    public function calculatePatientAndInsurancePortions(PayerType $payerType, float $amount): array { return $this->invoices->resolvePayerAmounts($payerType, $amount); }
    public function preventDuplicateBilling(DentalProcedure $procedure): void { if ($procedure->invoice_item_id) throw ValidationException::withMessages(['billing'=>'Procedure imeshachajiwa.']); }
    public function cancelOrReverseCharge(DentalProcedure $procedure): void { if ($procedure->invoice_item_id) $procedure->invoiceItem?->update(['status'=>'cancelled']); }
    public function summarizeTreatmentPlanCost($plan): float { return (float) $plan->items()->sum('total_amount'); }
    private function price(Service $service, Visit $visit)
    {
        $profile = $visit->payerProfile;
        $price = $this->pricing->getCurrentPrice($service, $visit->payer_type, $profile?->insurance_provider_id, $profile?->corporate_account_id);
        if ($service->requires_payment && ! $price) throw ValidationException::withMessages(['service_price'=>"Huduma ya {$service->name} bado haijawekewa bei ya {$visit->payer_type->label()}."]);
        return $price;
    }
    private function addServiceCharge(Invoice $invoice, Service $service, $actor, array $metadata, float $quantity = 1)
    {
        if ($invoice->items()->where('service_id', $service->id)->whereJsonContains('metadata->charge_source', $metadata['charge_source'])->exists()) return $invoice->items()->where('service_id', $service->id)->first();
        $price = $this->price($service, $invoice->visit);
        $total = (float) ($price?->amount ?? 0) * $quantity;
        $split = $this->calculatePatientAndInsurancePortions($invoice->payer_type, $total);
        return $invoice->items()->create(['service_id'=>$service->id,'item_type'=>$service->service_type->value,'description'=>$service->name,'quantity'=>$quantity,'unit_price'=>$price?->amount ?? 0,'total_amount'=>$total,'payer_amount'=>$split['payer_amount'],'insurance_amount'=>$split['insurance_amount'],'patient_amount'=>$split['patient_amount'],'status'=>$invoice->payer_type === PayerType::Cash ? 'pending' : 'covered','metadata'=>array_merge($metadata, ['service_code'=>$service->code]),'created_by'=>$actor->id]);
    }
}
