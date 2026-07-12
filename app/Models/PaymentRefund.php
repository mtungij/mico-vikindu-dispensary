<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['facility_id','patient_id','invoice_id','payment_id','refund_number','amount','refund_method_id','reason','transaction_reference','status','requested_by','approved_by','approved_at','processed_by','processed_at','cashier_session_id','notes'])]
class PaymentRefund extends Model { use BelongsToCurrentFacility, HasFactory, SoftDeletes; protected function casts(): array { return ['amount'=>'decimal:2','approved_at'=>'datetime','processed_at'=>'datetime']; } }
