<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['facility_id','payment_id','reversal_number','amount','reason','requested_by','approved_by','approved_at','reversed_by','reversed_at','status'])]
class PaymentReversal extends Model { use BelongsToCurrentFacility, HasFactory, SoftDeletes; protected function casts(): array { return ['amount'=>'decimal:2','approved_at'=>'datetime','reversed_at'=>'datetime']; } }
