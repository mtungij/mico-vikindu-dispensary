<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedCleaningRecord;
use App\Models\MedicationAdministration;
use App\Models\NursingObservation;
use App\Models\ObservationAdmission;
use App\Models\ObservationDischarge;

class ObservationReportService { public function admissions() { return ObservationAdmission::query()->forCurrentFacility()->with(['patient','bed','room'])->latest('admitted_at'); } public function occupancy(): array { return ['total'=>Bed::query()->forCurrentFacility()->count(),'available'=>Bed::query()->forCurrentFacility()->where('status','available')->count(),'occupied'=>Bed::query()->forCurrentFacility()->where('status','occupied')->count(),'cleaning'=>Bed::query()->forCurrentFacility()->where('status','cleaning')->count()]; } public function nursing() { return NursingObservation::query()->forCurrentFacility()->with(['patient','admission'])->latest('recorded_at'); } public function medication() { return MedicationAdministration::query()->forCurrentFacility()->latest('scheduled_at'); } public function discharges() { return ObservationDischarge::query()->forCurrentFacility()->with('patient')->latest('discharged_at'); } public function cleaning() { return BedCleaningRecord::query()->forCurrentFacility()->with('bed')->latest('requested_at'); } }
