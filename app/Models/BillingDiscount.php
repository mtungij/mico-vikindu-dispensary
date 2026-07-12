<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['facility_id','invoice_id','invoice_item_id','discount_type','discount_value','discount_amount','reason','requested_by','approved_by','approved_at','status'])]
class BillingDiscount extends Model { use BelongsToCurrentFacility, HasFactory, SoftDeletes; protected function casts(): array { return ['discount_value'=>'decimal:2','discount_amount'=>'decimal:2','approved_at'=>'datetime']; } }
