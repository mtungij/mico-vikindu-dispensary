<?php

namespace App\Enums;

enum IvFluidStatus: string { case Planned = 'planned'; case Running = 'running'; case Paused = 'paused'; case Completed = 'completed'; case Stopped = 'stopped'; case Cancelled = 'cancelled'; }
