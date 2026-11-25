<?php

namespace App\Services;

use App\Enums\RchService;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\RchChild;
use App\Models\RchEncounter;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RchProgrammeVisitService
{
    public function __construct(private readonly RchWorkflowService $workflow) {}

    public function open(int $visitId, RchService $programme, User $actor, ?string $permission = null): RchEncounter
    {
        Gate::forUser($actor)->authorize($permission ?? $programme->visitPermission());
        $visit = Visit::query()->forCurrentFacility()->whereHas('consultationService', fn ($query) => $query
            ->where('facility_id', currentFacility()?->id)->where('code', $programme->value))->findOrFail($visitId);

        try {
            $encounter = $this->workflow->startEncounter($visit, $actor);
        } catch (ValidationException $exception) {
            abort(409, $exception->getMessage());
        }

        $this->authorizeContext($encounter, $programme, $actor, $permission);
        if ($visit->visit_status !== VisitStatus::Completed && ! $this->workflow->initialChargeCleared($visit->refresh())) {
            abort(409, __('rch.programme_payment_required'));
        }

        return $encounter;
    }

    public function authorizeContext(RchEncounter $encounter, RchService $programme, User $actor, ?string $permission = null): void
    {
        Gate::forUser($actor)->authorize($permission ?? $programme->visitPermission());
        Gate::forUser($actor)->authorize('rch.start-encounter');
        abort_unless($actor->belongsToCurrentFacility() && $encounter->facility_id === currentFacility()?->id
            && $encounter->encounter_type === $programme->encounterType()
            && $encounter->visit?->facility_id === $encounter->facility_id && $encounter->visit?->patient_id === $encounter->patient_id
            && $encounter->patient?->facility_id === $encounter->facility_id, 404);
    }

    public function childProfile(Patient $patient, User $actor): RchChild
    {
        $children = app(RchChildService::class);

        return $children->profileForProgramme($patient) ?? $children->register($patient, [], $actor);
    }

    public function save(RchEncounter $encounter, RchService $programme, User $actor, callable $write, ?string $permission = null): mixed
    {
        Gate::forUser($actor)->authorize($permission ?? $programme->visitPermission());

        return $this->workflow->withOpenEncounter($encounter, $actor, function (RchEncounter $linked, Visit $visit) use ($programme, $write): mixed {
            abort_unless($linked->encounter_type === $programme->encounterType()
                && $visit->consultationService?->code === $programme->value, 404);
            if (! $this->workflow->initialChargeCleared($visit)) {
                throw ValidationException::withMessages(['visit' => __('rch.programme_payment_required')]);
            }
            // Programme profiles may be shared by different visits for the same patient.
            $patient = $visit->patient()->lockForUpdate()->firstOrFail();

            return $write($linked, $visit, $patient);
        });
    }
}
