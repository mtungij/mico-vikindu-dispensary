<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['facility_id','invoice_id','invoice_item_id','adjustment_type','amount','reason','status','requested_by','approved_by','approved_at','applied_at','metadata'])]
class InvoiceAdjustment extends Model { use BelongsToCurrentFacility, HasFactory, SoftDeletes; protected function casts(): array { return ['amount'=>'decimal:2','approved_at'=>'datetime','applied_at'=>'datetime','metadata'=>'array']; } }
