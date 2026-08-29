<?php

namespace App\Services;

use App\Models\PrescriptionItem;

class MedicineFinancialClearanceService
{
    /**
     * Derive medicine clearance from immutable billing values. No clearance state is stored.
     *
     * @return array{prescribed_quantity: string, patient_amount_due: string, patient_amount_paid: string, unit_patient_price: string, billed_unit_price: string, paid_quantity: string, dispensed_quantity: string, remaining_prescribed_quantity: string, remaining_paid_quantity: string, unpaid_quantity: string, authorization_pending: bool, financially_cleared: bool}
     */
    public function forItem(PrescriptionItem $item): array
    {
        $item->loadMissing(['invoiceItem', 'medicine.dispensingUnit']);
        $invoiceItem = $item->invoiceItem;
        $prescribed = $this->quantity($item->quantity ?? 0);
        $dispensed = $this->quantity($item->dispensed_quantity ?? 0);
        $remaining = $this->maximum('0.000', bcsub($prescribed, $dispensed, 3));
        $patientDue = $this->money($invoiceItem?->patient_amount ?? 0);
        $patientPaid = $this->minimum($patientDue, $this->money($invoiceItem?->paid_amount ?? 0));
        $authorizationPending = $this->authorizationPending($invoiceItem);
        $activeBillingItem = $invoiceItem && ! in_array($invoiceItem->status, ['cancelled', 'reversed', 'non_billable'], true);

        if (! $activeBillingItem || $authorizationPending) {
            $paidQuantity = '0.000';
        } elseif (bccomp($patientDue, '0.00', 2) === 0) {
            // Existing policy treats approved/claimable insurance liability as financially covered.
            $paidQuantity = $prescribed;
        } else {
            $rawCovered = bcdiv(bcmul($prescribed, $patientPaid, 5), $patientDue, 5);
            $paidQuantity = $this->floorQuantity($rawCovered, (bool) $item->medicine?->dispensingUnit?->decimal_allowed);
            $paidQuantity = $this->minimum($prescribed, $paidQuantity);
        }

        $remainingPaid = $this->minimum($remaining, $this->maximum('0.000', bcsub($paidQuantity, $dispensed, 3)));
        $unpaid = $this->maximum('0.000', bcsub($prescribed, $paidQuantity, 3));
        $unitPatientPrice = bccomp($prescribed, '0.000', 3) > 0
            ? bcdiv($patientDue, $prescribed, 2)
            : '0.00';

        return [
            'prescribed_quantity' => $prescribed,
            'patient_amount_due' => $patientDue,
            'patient_amount_paid' => $patientPaid,
            'unit_patient_price' => $unitPatientPrice,
            'billed_unit_price' => $this->money($invoiceItem?->unit_price ?? $item->unit_price_snapshot ?? 0),
            'paid_quantity' => $paidQuantity,
            'dispensed_quantity' => $dispensed,
            'remaining_prescribed_quantity' => $remaining,
            'remaining_paid_quantity' => $remainingPaid,
            'unpaid_quantity' => $unpaid,
            'authorization_pending' => $authorizationPending,
            'financially_cleared' => bccomp($paidQuantity, $prescribed, 3) >= 0 && bccomp($prescribed, '0.000', 3) > 0,
        ];
    }

    private function authorizationPending($invoiceItem): bool
    {
        if (! $invoiceItem) {
            return false;
        }

        $snapshot = $invoiceItem->coverage_snapshot ?? [];

        return (bool) ($snapshot['requires_pre_authorization'] ?? false)
            && ! $invoiceItem->insurance_pre_authorization_id;
    }

    private function floorQuantity(string $quantity, bool $decimalAllowed): string
    {
        $factor = $decimalAllowed ? '1000' : '1';
        $floored = bcdiv(bcmul($quantity, $factor, 5), '1', 0);

        return bcdiv($floored, $factor, 3);
    }

    private function quantity(mixed $value): string
    {
        return bcadd((string) $value, '0', 3);
    }

    private function money(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    private function minimum(string $left, string $right): string
    {
        return bccomp($left, $right, 3) <= 0 ? $left : $right;
    }

    private function maximum(string $left, string $right): string
    {
        return bccomp($left, $right, 3) >= 0 ? $left : $right;
    }
}
