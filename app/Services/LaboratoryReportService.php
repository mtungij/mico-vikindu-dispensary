<?php

namespace App\Services;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;

class LaboratoryReportService
{
    public function verifierSignaturePath(LaboratoryResult $result): ?string
    {
        return $result->verifier?->staffProfile?->activeSignature?->signature_path;
    }

    public function orderReleasedResults(LaboratoryOrder $order)
    {
        return $order->results()->with(['test', 'values', 'verifier.staffProfile.activeSignature'])->whereIn('result_status', ['verified', 'released'])->get();
    }
}
