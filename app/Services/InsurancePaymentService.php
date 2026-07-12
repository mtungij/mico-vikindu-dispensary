<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\InsurancePayment;
use Illuminate\Support\Facades\DB;

class InsurancePaymentService
{
    public function __construct(protected InsuranceAuditService $audit) {}

    public function calculateUnallocatedBalance(InsurancePayment $payment): float
    {
        return round((float) $payment->amount - (float) $payment->allocations()->sum('allocated_amount'), 2);
    }

    public function allocate(InsurancePayment $payment, InsuranceClaim $claim, float $amount): void
    {
        DB::transaction(function () use ($payment, $claim, $amount): void {
            $payment = InsurancePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $claim = InsuranceClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($amount > $this->calculateUnallocatedBalance($payment)) throw new \RuntimeException('Allocation exceeds payment balance.');
            if ($amount > (float) $claim->outstanding_amount) throw new \RuntimeException('Allocation exceeds claim outstanding amount.');
            $payment->allocations()->create(['insurance_claim_id' => $claim->id, 'allocated_amount' => $amount, 'allocated_by' => auth()->id(), 'allocated_at' => now()]);
            $paid = (float) $claim->paid_amount + $amount;
            $outstanding = max(0, (float) $claim->payer_claimed_amount - $paid);
            $claim->update(['paid_amount' => $paid, 'outstanding_amount' => $outstanding, 'status' => $outstanding > 0 ? 'partially_paid' : 'paid', 'paid_at' => $outstanding > 0 ? null : now()]);
            $payment->update(['status' => $this->calculateUnallocatedBalance($payment) > 0 ? 'partially_allocated' : 'fully_allocated']);
            $this->audit->record('payment_allocated', $claim, ['amount' => $amount]);
        });
    }
}
