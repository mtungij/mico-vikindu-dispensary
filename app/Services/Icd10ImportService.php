<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Icd10Code;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use SplFileObject;
use Throwable;

class Icd10ImportService
{
    private const CHUNK_SIZE = 500;

    private const HEADER_ALIASES = [
        'code' => ['code', 'icd_code', 'diagnosis_code'],
        'title' => ['title', 'diagnosis', 'diagnosis_name', 'description_short'],
        'description' => ['description', 'long_description'],
        'chapter' => ['chapter', 'chapter_name'],
        'category' => ['category', 'category_name'],
    ];

    /**
     * @return array{source_file: string, detected_headers: array<int, string>, total: int, inserted: int, updated: int, unchanged: int, skipped: int, failed: int, failures: array<int, array{row: int, reason: string}>, dry_run: bool}
     */
    public function import(
        string $path,
        ?string $source = null,
        ?string $version = null,
        bool $dryRun = false,
        ?User $actor = null,
    ): array {
        $resolvedPath = $this->resolvePath($path);
        $csv = new SplFileObject($resolvedPath, 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);

        $rawHeaders = $csv->fgetcsv();
        if (! is_array($rawHeaders) || $rawHeaders === [null]) {
            throw new InvalidArgumentException('The CSV file does not contain a header row.');
        }

        $headers = array_map($this->normalizeHeader(...), $rawHeaders);
        $fieldIndexes = $this->mapHeaders($headers);
        $sourceName = filled($source) ? trim($source) : basename($resolvedPath);
        $sourceVersion = filled($version) ? trim($version) : null;
        $result = [
            'source_file' => $resolvedPath,
            'detected_headers' => $headers,
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failures' => [],
            'dry_run' => $dryRun,
        ];
        $chunk = [];
        $seenCodes = [];
        $rowNumber = 1;

        while (! $csv->eof()) {
            $row = $csv->fgetcsv();
            $rowNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $result['total']++;
            if (! is_array($row) || count($row) !== count($headers)) {
                $this->recordFailure($result, $rowNumber, 'Column count does not match the header row.');

                continue;
            }

            $normalized = $this->normalizeRow($row, $fieldIndexes, $rowNumber);
            if ($normalized['code'] === '' || $normalized['title'] === '') {
                $result['skipped']++;
                $this->recordFailure($result, $rowNumber, 'Code and title are required.', false);

                continue;
            }

            if (isset($seenCodes[$normalized['code']])) {
                $result['skipped']++;
                $this->recordFailure($result, $rowNumber, "Duplicate code {$normalized['code']} in source file.", false);

                continue;
            }

            $seenCodes[$normalized['code']] = true;
            $chunk[] = $normalized;

            if (count($chunk) >= self::CHUNK_SIZE) {
                $this->processChunk($chunk, $sourceName, $sourceVersion, $dryRun, $result);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->processChunk($chunk, $sourceName, $sourceVersion, $dryRun, $result);
        }

        $this->audit($result, $sourceName, $sourceVersion, $actor);

        return $result;
    }

    private function resolvePath(string $path): string
    {
        $candidate = $path;
        if (! str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
            $candidate = base_path($candidate);
        }

        $resolved = realpath($candidate);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new InvalidArgumentException("ICD-10 CSV file does not exist or is not readable: {$path}");
        }

        return $resolved;
    }

    private function normalizeHeader(mixed $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? '';

        return str($header)->trim()->lower()->replace([' ', '-'], '_')->toString();
    }

    /** @return array<string, int|null> */
    private function mapHeaders(array $headers): array
    {
        $mapped = [];
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            $mapped[$field] = null;
            foreach ($aliases as $alias) {
                $index = array_search($alias, $headers, true);
                if ($index !== false) {
                    $mapped[$field] = $index;
                    break;
                }
            }
        }

        foreach (['code', 'title'] as $required) {
            if ($mapped[$required] === null) {
                throw new InvalidArgumentException("Missing required CSV header for {$required}. Supported aliases: ".implode(', ', self::HEADER_ALIASES[$required]).'.');
            }
        }

        return $mapped;
    }

    private function isBlankRow(mixed $row): bool
    {
        if (! is_array($row) || $row === [null]) {
            return true;
        }

        return collect($row)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    /** @return array{code: string, title: string, description: ?string, chapter: ?string, category: ?string, source_row: int} */
    private function normalizeRow(array $row, array $fieldIndexes, int $rowNumber): array
    {
        $value = static function (string $field) use ($row, $fieldIndexes): ?string {
            $index = $fieldIndexes[$field];
            if ($index === null) {
                return null;
            }

            $value = trim((string) ($row[$index] ?? ''));

            return $value === '' ? null : $value;
        };

        return [
            'code' => mb_strtoupper($value('code') ?? ''),
            'title' => $value('title') ?? '',
            'description' => $value('description'),
            'chapter' => $value('chapter'),
            'category' => $value('category'),
            'source_row' => $rowNumber,
        ];
    }

    private function processChunk(array $rows, string $source, ?string $version, bool $dryRun, array &$result): void
    {
        $delta = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0];

        try {
            $process = function () use ($rows, $source, $version, $dryRun, &$delta): void {
                foreach ($rows as $row) {
                    $existing = Icd10Code::query()->where('code', $row['code'])->first();
                    $existingMetadata = $existing?->metadata ?? [];
                    unset($existingMetadata['imported_at']);
                    $metadata = array_merge($existingMetadata, array_filter([
                        'import_source' => $source,
                        'source_version' => $version,
                        'source_row' => $row['source_row'],
                    ], fn (mixed $value): bool => $value !== null));
                    $values = [
                        'title' => $row['title'],
                        'description' => $row['description'],
                        'chapter' => $row['chapter'],
                        'category' => $row['category'],
                        'is_active' => true,
                        'is_billable' => $existing?->is_billable ?? true,
                        'metadata' => $metadata,
                    ];

                    if ($existing === null) {
                        $delta['inserted']++;
                    } elseif ($this->isUnchanged($existing, $values)) {
                        $delta['unchanged']++;

                        continue;
                    } else {
                        $delta['updated']++;
                    }

                    if (! $dryRun) {
                        $values['metadata']['imported_at'] = now()->toIso8601String();
                        Icd10Code::query()->updateOrCreate(['code' => $row['code']], $values);
                    }
                }
            };

            if ($dryRun) {
                $process();
            } else {
                DB::transaction($process);
            }

            foreach ($delta as $metric => $count) {
                $result[$metric] += $count;
            }
        } catch (Throwable $exception) {
            foreach ($rows as $row) {
                $this->recordFailure($result, $row['source_row'], 'Database import failed: '.$exception->getMessage());
            }
        }
    }

    private function isUnchanged(Icd10Code $existing, array $values): bool
    {
        foreach (['title', 'description', 'chapter', 'category', 'is_active', 'is_billable'] as $field) {
            if ($existing->{$field} !== $values[$field]) {
                return false;
            }
        }

        $existingMetadata = $existing->metadata ?? [];
        unset($existingMetadata['imported_at']);

        return $existingMetadata === $values['metadata'];
    }

    private function recordFailure(array &$result, int $row, string $reason, bool $failed = true): void
    {
        if ($failed) {
            $result['failed']++;
        }
        $result['failures'][] = ['row' => $row, 'reason' => str($reason)->limit(250)->toString()];
    }

    private function audit(array $result, string $source, ?string $version, ?User $actor): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'event' => 'icd10.catalogue_imported',
            'subject_type' => Icd10Code::class,
            'new_values' => [
                'actor' => $actor?->email ?? 'system/CLI',
                'source' => $source,
                'source_version' => $version,
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'unchanged' => $result['unchanged'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'dry_run' => $result['dry_run'],
            ],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? 'CLI' : request()->userAgent(),
        ]);
    }
}
