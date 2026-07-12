<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model;
#[Fillable(['facility_id','patient_id','transaction_type','reference_type','reference_id','amount','balance_after','reason','created_by','created_at'])]
class PatientCreditTransaction extends Model { use BelongsToCurrentFacility, HasFactory; public const UPDATED_AT = null; protected function casts(): array { return ['amount'=>'decimal:2','balance_after'=>'decimal:2']; } }
