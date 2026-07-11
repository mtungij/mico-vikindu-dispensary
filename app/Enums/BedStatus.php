<?php

namespace App\Enums;

enum BedStatus: string { case Available = 'available'; case Reserved = 'reserved'; case Occupied = 'occupied'; case Cleaning = 'cleaning'; case Maintenance = 'maintenance'; case OutOfService = 'out_of_service'; case Blocked = 'blocked'; }
