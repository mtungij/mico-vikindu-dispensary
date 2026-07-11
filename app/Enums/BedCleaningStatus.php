<?php

namespace App\Enums;

enum BedCleaningStatus: string { case Clean = 'clean'; case NeedsCleaning = 'needs_cleaning'; case CleaningInProgress = 'cleaning_in_progress'; case Disinfected = 'disinfected'; }
