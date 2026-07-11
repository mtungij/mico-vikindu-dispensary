<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\ObservationAdmission;
use App\Models\Service;

class ObservationBillingService
{
    public function __construct(private readonly InvoiceService $invoices, private readonly ServicePricingService $pricing) {}
    public function createAdmissionCharges(ObservationAdmission $admission, ?Service $service, $actor): void { if (! $service) return; $invoice = $this->invoiceFor($admission, $actor); if ($invoice->items()->where('service_id',$service->id)->where('metadata->observation_admission_id',$admission->id)->exists()) return; $item = $this->invoices->addServiceItem($invoice, $service, $actor); $item->update(['metadata' => ['service_code'=>$service->code, 'observation_admission_id'=>$admission->id, 'billing_period'=>'admission']]); $this->invoices->calculateTotals($invoice); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_charge_added','subject_type'=>$item::class,'subject_id'=>$item->id]); }
    public function calculateHourlyCharge(ObservationAdmission $admission): float { $hours = max(1, ceil($admission->admitted_at->diffInMinutes($admission->actual_discharge_at ?? now()) / 60)); return $hours * (float) ($admission->bed?->hourly_rate ?? 0); }
    public function calculateSessionCharge(ObservationAdmission $admission): float { return (float) ($admission->bed?->session_rate ?? 0); }
    public function calculateDailyCharge(ObservationAdmission $admission): float { $days = max(1, ceil($admission->admitted_at->diffInHours($admission->actual_discharge_at ?? now()) / 24)); return $days * (float) ($admission->bed?->daily_rate ?? 0); }
    public function addNursingCharge(ObservationAdmission $a, ?Service $s, $actor): void { $this->createAdmissionCharges($a, $s, $actor); }
    public function addProcedureCharge(ObservationAdmission $a, Service $s, $actor) { $invoice = $this->invoiceFor($a, $actor); $item = $this->invoices->addServiceItem($invoice, $s, $actor); $item->update(['metadata'=>['observation_admission_id'=>$a->id,'billing_period'=>'procedure']]); $this->invoices->calculateTotals($invoice); return $item; }
    public function addOxygenCharge(ObservationAdmission $a, ?Service $s, $actor): void { $this->createAdmissionCharges($a, $s, $actor); }
    public function finalizeDischargeCharges(ObservationAdmission $admission, $actor): void { if ($invoice = $admission->visit->invoice) { $this->invoices->calculateTotals($invoice); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_bill_finalized','subject_type'=>$invoice::class,'subject_id'=>$invoice->id]); } }
    public function recalculateObservationInvoice(ObservationAdmission $admission): ?Invoice { return $admission->visit->invoice ? $this->invoices->calculateTotals($admission->visit->invoice) : null; }
    private function invoiceFor(ObservationAdmission $admission, $actor): Invoice { return $admission->visit->invoice ?: $this->invoices->createVisitInvoice($admission->visit, [], $actor); }
}
