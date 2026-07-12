<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['facility_id','patient_id','credit_limit','current_balance','payment_terms_days','status','approved_by','approved_at','expires_at','notes'])]
class PatientCreditProfile extends Model { use BelongsToCurrentFacility, HasFactory, SoftDeletes; protected function casts(): array { return ['credit_limit'=>'decimal:2','current_balance'=>'decimal:2','approved_at'=>'datetime','expires_at'=>'datetime']; } }
