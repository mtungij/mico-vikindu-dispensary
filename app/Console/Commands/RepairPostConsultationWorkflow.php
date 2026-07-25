<?php

namespace App\Console\Commands;

use App\Enums\ClinicalEncounterStatus;
use App\Models\ActivityLog;
use App\Models\ClinicalEncounter;
use App\Models\PatientQueue;
use App\Services\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairPostConsultationWorkflow extends Command
{
    protected $signature = 'workflow:repair-post-consultation {--apply : Apply only unambiguous repairs}';

    protected $description = 'Report and safely repair unambiguous post-consultation workflow contradictions';

    public function handle(WorkflowService $workflow): int
    {
        $apply = (bool) $this->option('apply');
        $repairedEncounters = 0;
        $repairedOpdQueues = 0;

        ClinicalEncounter::query()
            ->whereNotNull('completed_at')
            ->whereIn('status', ['awaiting_results', 'awaiting_lab', 'awaiting_pharmacy', 'awaiting_procedure'])
            ->orderBy('id')
            ->chunkById(100, function ($encounters) use ($apply, &$repairedEncounters): void {
                foreach ($encounters as $encounter) {
                    $this->line("Contradictory completed encounter: {$encounter->id}");
                    if (! $apply) {
                        continue;
                    }
                    DB::transaction(function () use ($encounter, &$repairedEncounters): void {
                        $locked = ClinicalEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
                        $old = $locked->status?->value ?? $locked->status;
                        $locked->update(['status' => ClinicalEncounterStatus::Completed]);
                        ActivityLog::query()->create([
                            'user_id' => $locked->completed_by ?? $locked->updated_by ?? $locked->created_by,
                            'event' => 'post_consultation_legacy_repaired',
                            'subject_type' => $locked::class,
                            'subject_id' => $locked->id,
                            'old_values' => ['status' => $old],
                            'new_values' => ['status' => ClinicalEncounterStatus::Completed->value],
                        ]);
                        $repairedEncounters++;
                    });
                }
            });

        PatientQueue::query()
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->whereHas('department', fn ($query) => $query->where('code', 'OPD'))
            ->whereHas('visit', fn ($query) => $query
                ->where('visit_status', '!=', 'awaiting_doctor_review')
                ->whereHas('clinicalEncounters', fn ($encounters) => $encounters
                    ->whereNotNull('completed_at')
                    ->whereIn('status', ['completed', 'referred'])))
            ->with('visit')
            ->orderBy('id')
            ->chunkById(100, function ($queues) use ($apply, $workflow, &$repairedOpdQueues): void {
                foreach ($queues as $queue) {
                    $this->line("Active OPD queue after completed encounter: {$queue->id}");
                    if (! $apply) {
                        continue;
                    }
                    $actor = $queue->visit->clinicalEncounters()
                        ->whereNotNull('completed_at')
                        ->latest('completed_at')
                        ->first()?->completer
                        ?? $queue->visit->clinicalEncounters()->latest()->first()?->provider;
                    if (! $actor) {
                        $this->warn("Skipped queue {$queue->id}: no safe actor could be resolved.");

                        continue;
                    }
                    $workflow->completeQueue($queue, $actor);
                    $repairedOpdQueues++;
                }
            });

        $duplicateGroups = PatientQueue::query()
            ->select(['visit_id', 'department_id', DB::raw('COUNT(*) as aggregate')])
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->groupBy('visit_id', 'department_id')
            ->having('aggregate', '>', 1)
            ->get();
        foreach ($duplicateGroups as $group) {
            $this->warn("Ambiguous duplicate active queues: visit {$group->visit_id}, department {$group->department_id}, count {$group->aggregate}. Not modified.");
        }

        $orphanQueues = PatientQueue::query()
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->whereHas('department', fn ($query) => $query->whereIn('code', ['PHA', 'LAB']))
            ->with(['department', 'visit'])
            ->get()
            ->filter(fn (PatientQueue $queue): bool => match ($queue->department->code) {
                'PHA' => ! $queue->visit->clinicalEncounters()->whereHas('prescriptions')->exists(),
                'LAB' => ! $queue->visit->clinicalEncounters()->whereHas('laboratoryOrders')->exists(),
                default => false,
            });
        foreach ($orphanQueues as $queue) {
            $this->warn("Ambiguous downstream queue without domain work: {$queue->id}. Not modified.");
        }

        $this->info(($apply ? 'Applied' : 'Dry run').' — encounters repaired: '.$repairedEncounters
            .'; OPD queues repaired: '.$repairedOpdQueues
            .'; duplicate groups reported: '.$duplicateGroups->count()
            .'; orphan queues reported: '.$orphanQueues->count());

        return self::SUCCESS;
    }
}
