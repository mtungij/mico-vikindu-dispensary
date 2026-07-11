<?php

namespace App\Events;

use App\Models\FacilityDocument;
use Illuminate\Foundation\Events\Dispatchable;

class FacilityDocumentUploaded
{
    use Dispatchable;

    public function __construct(public FacilityDocument $document) {}
}
