<?php

namespace App\Enums;

enum BedAssignmentStatus: string { case Active = 'active'; case Transferred = 'transferred'; case Released = 'released'; case Cancelled = 'cancelled'; }
