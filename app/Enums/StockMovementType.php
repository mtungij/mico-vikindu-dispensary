<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OpeningStock = 'opening_stock';
    case PurchaseReceipt = 'purchase_receipt';
    case Dispensing = 'dispensing';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ReturnFromPatient = 'return_from_patient';
    case ReturnToSupplier = 'return_to_supplier';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case Recall = 'recall';
    case StockCountGain = 'stock_count_gain';
    case StockCountLoss = 'stock_count_loss';
    case CancellationReversal = 'cancellation_reversal';
    case Other = 'other';
}
