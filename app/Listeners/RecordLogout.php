<?php

namespace App\Listeners;

use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Logout;

class RecordLogout
{
    public function handle(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $open = UserLoginHistory::query()
            ->where('user_id', $event->user->id)
            ->where('status', 'successful')
            ->whereNull('logged_out_at')
            ->latest()
            ->first();

        if ($open) {
            $open->update(['logged_out_at' => now(), 'status' => 'logged_out']);
            return;
        }

        UserLoginHistory::query()->create([
            'user_id' => $event->user->id,
            'facility_id' => $event->user->staffProfile?->facility_id,
            'email_attempted' => $event->user->email,
            'status' => 'logged_out',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'logged_out_at' => now(),
        ]);
    }
}
