<?php

namespace App\Listeners;

use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        $request = request();

        UserLoginHistory::query()->create([
            'user_id' => $event->user?->id,
            'facility_id' => $event->user?->staffProfile?->facility_id,
            'email_attempted' => str($event->credentials['email'] ?? $request->input('email'))->lower()->toString(),
            'status' => 'failed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'failure_reason' => 'Authentication failed.',
        ]);
    }
}
