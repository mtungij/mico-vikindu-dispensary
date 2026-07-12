<?php

namespace App\Http\Controllers\Insurance;

use App\Models\InsuranceClaimAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class InsuranceClaimAttachmentController
{
    public function view(InsuranceClaimAttachment $claimAttachment)
    {
        Gate::authorize('view', $claimAttachment);
        abort_unless(Storage::disk('private')->exists($claimAttachment->file_path), 404);

        return Storage::disk('private')->response($claimAttachment->file_path);
    }

    public function download(InsuranceClaimAttachment $claimAttachment)
    {
        Gate::authorize('view', $claimAttachment);
        abort_unless(Storage::disk('private')->exists($claimAttachment->file_path), 404);

        return Storage::disk('private')->download($claimAttachment->file_path, $claimAttachment->title);
    }
}
