<?php

namespace App\Support;

use Masmerise\Toaster\Toaster;

class Notifier
{
    public static function success(string $message = 'messages.saved'): void
    {
        Toaster::success(__($message));
    }

    public static function error(string $message = 'messages.failed'): void
    {
        Toaster::error(__($message));
    }

    public static function warning(string $message = 'messages.check_inputs'): void
    {
        Toaster::warning(__($message));
    }

    public static function info(string $message): void
    {
        Toaster::info(__($message));
    }
}
