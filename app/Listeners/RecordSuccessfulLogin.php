<?php

namespace App\Listeners;

use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $request = request();
        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        UserLoginHistory::query()->create([
            'user_id' => $event->user->id,
            'facility_id' => $event->user->staffProfile?->facility_id ?? currentFacility()?->id,
            'email_attempted' => $event->user->email,
            'status' => 'successful',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => now(),
        ]);
    }
}
