<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code','name','description','coding_system','parent_code','is_billable','is_active','metadata'])]
class DiagnosisCode extends Model
{
    use HasFactory;
    protected function casts(): array { return ['is_billable'=>'boolean','is_active'=>'boolean','metadata'=>'array']; }
}
