<?php

namespace App\Enums;

enum NursingTaskStatus: string { case Pending = 'pending'; case Due = 'due'; case InProgress = 'in_progress'; case Completed = 'completed'; case Overdue = 'overdue'; case Cancelled = 'cancelled'; }
