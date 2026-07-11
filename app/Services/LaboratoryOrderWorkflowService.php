<?php

namespace App\Services;

use App\Models\LaboratoryOrder;
use Illuminate\Validation\ValidationException;

class LaboratoryOrderWorkflowService
{
    public function ensureConfigured(LaboratoryOrder $order): void
    {
        if ($order->items()->whereNull('laboratory_test_id')->exists()) {
            throw ValidationException::withMessages(['order' => 'Baadhi ya services hazina laboratory test configuration.']);
        }
    }
}
