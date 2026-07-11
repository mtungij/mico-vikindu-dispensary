<?php

namespace App\Console\Commands;

use App\Models\Icd10Code;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportIcd10Codes extends Command
{
    protected $signature = 'icd10:import {file}';
    protected $description = 'Import ICD-10 foundation CSV columns: code,title,description,chapter,category.';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error('File haijasomeka.');
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            $this->error('CSV haina header.');
            return self::FAILURE;
        }
        $header = array_map(fn ($value) => str($value)->lower()->trim()->toString(), $header);
        foreach (['code', 'title'] as $required) {
            if (! in_array($required, $header, true)) {
                $this->error("Column {$required} inahitajika.");
                return self::FAILURE;
            }
        }

        $rows = [];
        $imported = 0;
        $errors = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (blank($data['code'] ?? null) || blank($data['title'] ?? null)) {
                $errors++;
                continue;
            }
            $rows[] = $data;
            if (count($rows) === 500) {
                $imported += $this->importChunk($rows);
                $rows = [];
                $this->output->write('.');
            }
        }
        fclose($handle);
        if ($rows) {
            $imported += $this->importChunk($rows);
        }

        $this->newLine();
        $this->info("Imported/updated {$imported} ICD-10 rows. Skipped errors: {$errors}.");

        return self::SUCCESS;
    }

    private function importChunk(array $rows): int
    {
        return DB::transaction(function () use ($rows): int {
            foreach ($rows as $row) {
                Icd10Code::query()->updateOrCreate(
                    ['code' => trim($row['code'])],
                    [
                        'title' => trim($row['title']),
                        'description' => $row['description'] ?? null,
                        'chapter' => $row['chapter'] ?? null,
                        'category' => $row['category'] ?? null,
                        'is_active' => true,
                        'is_billable' => true,
                    ],
                );
            }

            return count($rows);
        });
    }
}
