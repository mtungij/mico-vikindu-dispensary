<?php

namespace App\Http\Controllers\Dental;

use App\Http\Controllers\Controller;
use App\Models\DentalAttachment;
use App\Services\DentalAttachmentService;
use Illuminate\Support\Facades\Gate;

class DentalAttachmentController extends Controller
{
    public function view(DentalAttachment $dentalAttachment, DentalAttachmentService $service) { Gate::authorize('view', $dentalAttachment); return $service->response($dentalAttachment); }
    public function download(DentalAttachment $dentalAttachment, DentalAttachmentService $service) { Gate::authorize('view', $dentalAttachment); return $service->response($dentalAttachment, true); }
}
