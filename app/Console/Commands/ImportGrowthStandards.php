<?php

namespace App\Console\Commands;

use App\Models\GrowthReferenceStandard;
use Illuminate\Console\Command;

class ImportGrowthStandards extends Command
{
    protected $signature = 'growth-standards:import {file}';
    protected $description = 'Import growth reference standards from a CSV file. Values must come from a verified source.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error('File is not readable.');
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            if (! $data) {
                continue;
            }
            GrowthReferenceStandard::query()->updateOrCreate([
                'standard_name' => $data['standard_name'],
                'sex' => $data['sex'],
                'indicator' => $data['indicator'],
                'age_or_length_value' => $data['age_or_length_value'],
                'age_or_length_unit' => $data['age_or_length_unit'],
            ], $data);
            $count++;
        }
        fclose($handle);
        $this->info("Imported {$count} growth standard rows.");
        return self::SUCCESS;
    }
}
