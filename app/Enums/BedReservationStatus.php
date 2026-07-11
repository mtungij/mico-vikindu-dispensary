<?php

namespace App\Enums;

enum BedReservationStatus: string { case Active = 'active'; case Fulfilled = 'fulfilled'; case Expired = 'expired'; case Cancelled = 'cancelled'; }
