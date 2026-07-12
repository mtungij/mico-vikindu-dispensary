<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model;
#[Fillable(['patient_deposit_id','invoice_id','amount','applied_by','applied_at','reversed_at','reversal_reason'])]
class PatientDepositApplication extends Model { use HasFactory; protected function casts(): array { return ['amount'=>'decimal:2','applied_at'=>'datetime','reversed_at'=>'datetime']; } }
