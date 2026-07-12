<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model;
#[Fillable(['facility_id','patient_id','visit_id','invoice_id','handoff_id','override_type','amount_outstanding','reason','authorized_by','used_by','used_at','expires_at','status'])]
class BillingPaymentOverride extends Model { use BelongsToCurrentFacility, HasFactory; protected function casts(): array { return ['amount_outstanding'=>'decimal:2','used_at'=>'datetime','expires_at'=>'datetime']; } }
