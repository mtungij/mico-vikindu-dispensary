<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['facility_id','patient_id','deposit_number','amount','available_amount','payment_id','status','received_by','received_at','notes'])]
class PatientDeposit extends Model { use BelongsToCurrentFacility, HasFactory, SoftDeletes; protected function casts(): array { return ['amount'=>'decimal:2','available_amount'=>'decimal:2','received_at'=>'datetime']; } }
