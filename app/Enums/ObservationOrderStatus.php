<?php

namespace App\Enums;

enum ObservationOrderStatus: string { case Pending = 'pending'; case Acknowledged = 'acknowledged'; case InProgress = 'in_progress'; case Completed = 'completed'; case Cancelled = 'cancelled'; }
