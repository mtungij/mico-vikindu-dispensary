<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LaboratoryReportService
{
    public const INCOMPLETE_MESSAGE = 'Majibu ya vipimo bado hayajakamilika au kuthibitishwa.';

    public function __construct(private readonly SequenceNumberService $numbers) {}

    public function verifierSignaturePath(LaboratoryResult $result): ?string
    {
        return $result->verifier?->staffProfile?->activeSignature?->signature_path;
    }

    /** @return Collection<int, LaboratoryResult> */
    public function orderReleasedResults(LaboratoryOrder $order): Collection
    {
        return $this->eligibleResults($order);
    }

    public function isEligible(LaboratoryOrder $order): bool
    {
        try {
            $this->assertEligible($order);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function prepare(LaboratoryOrder $order): LaboratoryOrder
    {
        return DB::transaction(function () use ($order): LaboratoryOrder {
            $order = LaboratoryOrder::query()->lockForUpdate()->findOrFail($order->id);
            $results = $this->assertEligible($order);
            $revision = max(1, (int) $results->max('result_version'));

            if (! $order->report_number) {
                $order->update([
                    'report_number' => $this->numbers->next(
                        'laboratory_report_number_sequences',
                        $order->facility_id,
                        'LAB-RPT',
                    ),
                    'report_revision' => $revision,
                    'report_generated_at' => now(),
                ]);
            } elseif ((int) $order->report_revision !== $revision) {
                $order->update([
                    'report_revision' => $revision,
                    'report_generated_at' => now(),
                ]);
            }

            return $this->loadReportRelations($order->refresh());
        });
    }

    /** @return Collection<int, LaboratoryResult> */
    public function assertEligible(LaboratoryOrder $order): Collection
    {
        $order->loadMissing('visit:id,facility_id,patient_id');
        abort_unless(
            $order->facility_id === currentFacility()?->id
            && $order->visit?->facility_id === $order->facility_id
            && $order->patient_id === $order->visit?->patient_id,
            404,
        );

        $items = $order->items()
            ->whereNotIn('status', ['cancelled', 'entered_in_error'])
            ->with(['results' => fn ($query) => $query->latest('result_version')])
            ->get();
        if ($items->isEmpty()
            || $items->contains(fn ($item): bool => $item->status !== 'completed'
                || $item->result_status !== 'released'
                || ! $item->results->first()
                || $item->results->first()->result_status?->value !== 'released'
                || ! $item->results->first()->entered_by
                || ! $item->results->first()->entered_at
                || ! $item->results->first()->verified_by
                || ! $item->results->first()->verified_at
                || ! $item->results->first()->released_by
                || ! $item->results->first()->released_at)) {
            throw ValidationException::withMessages(['report' => self::INCOMPLETE_MESSAGE]);
        }

        return $items->map(fn ($item) => $item->results->first())->values();
    }

    public function recordAccess(LaboratoryOrder $order, User $actor, string $action): void
    {
        $actorKey = match ($action) {
            'viewed' => 'viewed_by',
            'downloaded' => 'downloaded_by',
            'printed' => 'printed_by',
            default => 'performed_by',
        };

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => "laboratory_report_{$action}",
            'subject_type' => $order::class,
            'subject_id' => $order->id,
            'new_values' => [
                'order_id' => $order->id,
                'patient_id' => $order->patient_id,
                'visit_id' => $order->visit_id,
                'action' => $action,
                $actorKey => $actor->id,
                'report_number' => $order->report_number,
                'revision' => $order->report_revision,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function loadReportRelations(LaboratoryOrder $order): LaboratoryOrder
    {
        return $order->load([
            'patient',
            'visit',
            'orderingClinician',
            'items.laboratoryTest',
            'samples.specimenType',
            'results' => fn ($query) => $query
                ->where('result_status', 'released')
                ->latest('result_version'),
            'results.values',
            'results.test',
            'results.sample.specimenType',
            'results.enterer.staffProfile.employmentRecord.jobTitle',
            'results.verifier.staffProfile.activeSignature',
            'results.verifier.staffProfile.employmentRecord.jobTitle',
            'results.releaser.staffProfile.employmentRecord.jobTitle',
        ]);
    }

    public function facilityLogoDataUri(?Facility $facility): ?string
    {
        return $this->dataUri('public', $facility?->logo_path);
    }

    /** @return array<int, string> */
    public function signatureDataUris(LaboratoryOrder $order): array
    {
        return $order->results
            ->mapWithKeys(function (LaboratoryResult $result): array {
                $path = $result->verifier?->staffProfile?->activeSignature?->signature_path;
                $uri = $this->dataUri('local', $path);

                return $uri ? [$result->id => $uri] : [];
            })
            ->all();
    }

    /** @return Collection<int, LaboratoryResult> */
    private function eligibleResults(LaboratoryOrder $order): Collection
    {
        $eligible = $this->assertEligible($order);
        $ids = $eligible->pluck('id');

        return LaboratoryResult::query()
            ->whereIn('id', $ids)
            ->with([
                'test',
                'values',
                'sample.specimenType',
                'enterer.staffProfile.employmentRecord.jobTitle',
                'verifier.staffProfile.activeSignature',
                'verifier.staffProfile.employmentRecord.jobTitle',
                'releaser.staffProfile.employmentRecord.jobTitle',
            ])
            ->get()
            ->sortBy(fn (LaboratoryResult $result) => $ids->search($result->id))
            ->values();
    }

    private function dataUri(string $disk, ?string $path): ?string
    {
        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $mime = Storage::disk($disk)->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk($disk)->get($path));
    }
}
