<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BillingNumberService
{
    public function payment(int $facilityId): string { return $this->next('payment_number_sequences', $facilityId, 'PAY'); }
    public function receipt(int $facilityId): string { return $this->next('receipt_number_sequences', $facilityId, 'RCT'); }
    public function cashierSession(int $facilityId): string { return $this->next('cashier_session_number_sequences', $facilityId, 'CSH'); }
    public function reversal(int $facilityId): string { return $this->next('payment_reversal_number_sequences', $facilityId, 'REV'); }
    public function refund(int $facilityId): string { return $this->next('payment_refund_number_sequences', $facilityId, 'REF'); }
    public function deposit(int $facilityId): string { return $this->next('patient_deposit_number_sequences', $facilityId, 'DEP'); }

    protected function next(string $table, int $facilityId, string $prefix): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($table, $facilityId, $prefix, $year): string {
            $sequence = DB::table($table)->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            if (! $sequence) {
                DB::table($table)->insert(['facility_id' => $facilityId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $sequence = DB::table($table)->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            }
            $next = (int) $sequence->last_number + 1;
            DB::table($table)->where('id', $sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('%s-%d-%06d', $prefix, $year, $next);
        });
    }
}
