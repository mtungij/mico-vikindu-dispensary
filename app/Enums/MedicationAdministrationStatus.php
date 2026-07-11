<?php

namespace App\Enums;

enum MedicationAdministrationStatus: string { case Scheduled = 'scheduled'; case Due = 'due'; case Administered = 'administered'; case Late = 'late'; case Omitted = 'omitted'; case Refused = 'refused'; case Held = 'held'; case Cancelled = 'cancelled'; }
