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
    protected function casts(): array { return ['is_billable' => 'boolean', 'is_active' => 'boolean', 'metadata' => 'array']; }
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('is_active', true)->where(fn ($q) => $q->where('code', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%"));
    }
}
