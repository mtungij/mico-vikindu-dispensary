<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case CoveredByInsurance = 'covered_by_insurance';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case WrittenOff = 'written_off';

    public function badge(): string
    {
        return match ($this) {
            self::Paid, self::CoveredByInsurance => 'success',
            self::Pending, self::PartiallyPaid, self::Draft => 'warning',
            default => 'danger',
        };
    }
}
