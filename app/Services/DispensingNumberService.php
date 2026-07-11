<?php

namespace App\Services;

class DispensingNumberService
{
    public function __construct(private readonly SequenceNumberService $sequences) {}
    public function next(int $facilityId): string { return $this->sequences->next('dispensing_number_sequences', $facilityId, 'DSP', 6); }
    public function purchaseOrder(int $facilityId): string { return $this->sequences->next('purchase_order_number_sequences', $facilityId, 'PO', 6); }
    public function receipt(int $facilityId): string { return $this->sequences->next('purchase_receipt_number_sequences', $facilityId, 'RCV', 6); }
    public function transfer(int $facilityId): string { return $this->sequences->next('stock_transfer_number_sequences', $facilityId, 'TRF', 6); }
    public function adjustment(int $facilityId): string { return $this->sequences->next('stock_adjustment_number_sequences', $facilityId, 'ADJ', 6); }
    public function count(int $facilityId): string { return $this->sequences->next('stock_count_number_sequences', $facilityId, 'CNT', 6); }
    public function patientReturn(int $facilityId): string { return $this->sequences->next('pharmacy_return_number_sequences', $facilityId, 'PRN', 6); }
    public function supplierReturn(int $facilityId): string { return $this->sequences->next('supplier_return_number_sequences', $facilityId, 'SRN', 6); }
}
