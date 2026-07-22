<?php

namespace App\Console\Commands;

use App\Services\Icd10ImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportIcd10Codes extends Command
{
    protected $signature = 'icd10:import
        {file : Absolute path or project-relative path to the CSV file}
        {--dry-run : Validate and report changes without writing ICD-10 records}
        {--source= : Approved catalogue source name}
        {--source-version= : Catalogue source version}';

    protected $description = 'Safely import an approved ICD-10 CSV catalogue.';

    public function handle(Icd10ImportService $importer): int
    {
        try {
            $result = $importer->import(
                path: $this->argument('file'),
                source: $this->option('source'),
                version: $this->option('source-version'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($result['dry_run'] ? 'ICD-10 dry run completed.' : 'ICD-10 import completed.');
        $this->line('Source file: '.$result['source_file']);
        $this->line('Detected headers: '.implode(', ', $result['detected_headers']));
        $this->table(['Metric', 'Count'], [
            ['Total rows processed', $result['total']],
            ['Inserted', $result['inserted']],
            ['Updated', $result['updated']],
            ['Unchanged', $result['unchanged']],
            ['Skipped', $result['skipped']],
            ['Failed', $result['failed']],
        ]);

        foreach ($result['failures'] as $failure) {
            $this->warn("Row {$failure['row']}: {$failure['reason']}");
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
