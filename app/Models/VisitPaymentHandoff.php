<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','invoice_id','source_department_id','destination_department_id','destination_type','destination_reference_type','destination_reference_id','reason','required_patient_amount','status','priority','created_by','released_by','released_at','cancelled_at','cancellation_reason'])]
class VisitPaymentHandoff extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['required_patient_amount'=>'decimal:2','released_at'=>'datetime','cancelled_at'=>'datetime']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function destinationDepartment(): BelongsTo { return $this->belongsTo(Department::class, 'destination_department_id'); }
}
