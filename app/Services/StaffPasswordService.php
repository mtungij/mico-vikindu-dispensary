<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffPasswordService
{
    public function generateTemporaryPassword(): string
    {
        return Str::password(12, true, true, true, false);
    }

    public function resetPassword(User $user, string $password, User $actor): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
            'password_changed_at' => now(),
        ])->save();

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'password_reset',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => [],
            'new_values' => ['must_change_password' => true],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function invalidateSessionsIfConfigured(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
