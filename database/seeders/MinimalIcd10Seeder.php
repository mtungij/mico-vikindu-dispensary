<?php

namespace Database\Seeders;

use App\Models\Icd10Code;
use Illuminate\Database\Seeder;

class MinimalIcd10Seeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'A09', 'title' => 'Infectious gastroenteritis and colitis, unspecified', 'chapter' => 'Development sample'],
            ['code' => 'B54', 'title' => 'Unspecified malaria', 'chapter' => 'Development sample'],
            ['code' => 'E11', 'title' => 'Type 2 diabetes mellitus', 'chapter' => 'Development sample'],
            ['code' => 'I10', 'title' => 'Essential hypertension', 'chapter' => 'Development sample'],
            ['code' => 'J06.9', 'title' => 'Acute upper respiratory infection, unspecified', 'chapter' => 'Development sample'],
            ['code' => 'J18.9', 'title' => 'Pneumonia, unspecified organism', 'chapter' => 'Development sample'],
            ['code' => 'K29.7', 'title' => 'Gastritis, unspecified', 'chapter' => 'Development sample'],
            ['code' => 'N39.0', 'title' => 'Urinary tract infection, site not specified', 'chapter' => 'Development sample'],
            ['code' => 'O80', 'title' => 'Single spontaneous delivery', 'chapter' => 'Development sample'],
            ['code' => 'R50.9', 'title' => 'Fever, unspecified', 'chapter' => 'Development sample'],
        ];

        foreach ($codes as $code) {
            Icd10Code::query()->updateOrCreate(
                ['code' => $code['code']],
                [...$code, 'description' => 'Minimal development seed. Import an official dataset for production use.', 'is_billable' => true, 'is_active' => true],
            );
        }
    }
}
