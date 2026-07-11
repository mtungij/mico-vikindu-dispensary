<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobTitleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): JobTitle
    {
        return DB::transaction(function () use ($data, $user): JobTitle {
            $data['facility_id'] = currentFacility()?->id;
            $data['code'] = str($data['code'])->upper()->toString();
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            $jobTitle = JobTitle::query()->create($data);
            $this->log($user, 'job_title.created', $jobTitle, [], $jobTitle->getAttributes());

            return $jobTitle;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobTitle $jobTitle, array $data, User $user): JobTitle
    {
        return DB::transaction(function () use ($jobTitle, $data, $user): JobTitle {
            $oldValues = $jobTitle->only(array_keys($data));
            $data['code'] = str($data['code'])->upper()->toString();
            $data['updated_by'] = $user->id;

            $jobTitle->update($data);
            $this->log($user, 'job_title.updated', $jobTitle, $oldValues, $data);

            return $jobTitle->refresh();
        });
    }

    public function toggleStatus(JobTitle $jobTitle, User $user): JobTitle
    {
        return DB::transaction(function () use ($jobTitle, $user): JobTitle {
            $oldValues = ['is_active' => $jobTitle->is_active];
            $jobTitle->update([
                'is_active' => ! $jobTitle->is_active,
                'updated_by' => $user->id,
            ]);
            $this->log($user, 'job_title.status_changed', $jobTitle, $oldValues, ['is_active' => $jobTitle->is_active]);

            return $jobTitle->refresh();
        });
    }

    public function delete(JobTitle $jobTitle, User $user): void
    {
        if ($jobTitle->users()->exists()) {
            throw ValidationException::withMessages([
                'job_title' => 'Cheo hiki kina watumishi waliounganishwa.',
            ]);
        }

        DB::transaction(function () use ($jobTitle, $user): void {
            $oldValues = $jobTitle->getAttributes();
            $jobTitle->update(['updated_by' => $user->id]);
            $jobTitle->delete();
            $this->log($user, 'job_title.deleted', $jobTitle, $oldValues, []);
        });
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function log(User $user, string $event, JobTitle $jobTitle, array $oldValues, array $newValues): void
    {
        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'subject_type' => JobTitle::class,
            'subject_id' => $jobTitle->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
