<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\PrescriptionItem;
use Illuminate\Validation\ValidationException;

class PharmacySubstitutionService
{
    public function validate(PrescriptionItem $item, Medicine $substitute, $actor): void
    {
        if (! $item->substitution_allowed) throw ValidationException::withMessages(['substitution' => 'Substitution hairuhusiwi.']);
        $original = $item->medicine;
        $equivalent = $original && $original->generic_medicine_id === $substitute->generic_medicine_id && $original->dosage_form_id === $substitute->dosage_form_id;
        if ($equivalent && ! $actor->can('pharmacy.substitute-equivalent')) throw ValidationException::withMessages(['substitution' => 'Huna ruhusa.']);
        if (! $equivalent && ! $actor->can('pharmacy.substitute-non-equivalent')) throw ValidationException::withMessages(['substitution' => 'Non-equivalent substitution inahitaji ruhusa.']);
    }
}
