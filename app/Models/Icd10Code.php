<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'title', 'description', 'chapter', 'category', 'is_billable', 'is_active', 'metadata'])]
class Icd10Code extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_billable' => 'boolean', 'is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $codeTerm = mb_strtoupper($term);
        $containsTerm = "%{$term}%";

        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) use ($containsTerm): void {
                $query
                    ->where('code', 'like', $containsTerm)
                    ->orWhere('title', 'like', $containsTerm)
                    ->orWhere('description', 'like', $containsTerm)
                    ->orWhere('metadata', 'like', $containsTerm);
            })
            ->orderByRaw(
                'CASE
                    WHEN UPPER(code) = ? THEN 0
                    WHEN UPPER(code) LIKE ? THEN 1
                    WHEN title LIKE ? THEN 2
                    WHEN description LIKE ? THEN 3
                    ELSE 4
                END',
                [$codeTerm, "{$codeTerm}%", $containsTerm, $containsTerm],
            )
            ->orderBy('code');
    }
}
