<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['dental_treatment_plan_id','service_id','tooth_number','surfaces','description_snapshot','quantity','unit_price_snapshot','total_amount','sequence_order','status','scheduled_date','completed_at','completed_by','invoice_item_id','notes'])]
class DentalTreatmentPlanItem extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['surfaces'=>'array','quantity'=>'decimal:2','unit_price_snapshot'=>'decimal:2','total_amount'=>'decimal:2','scheduled_date'=>'date','completed_at'=>'datetime']; }
    public function plan(): BelongsTo { return $this->belongsTo(DentalTreatmentPlan::class, 'dental_treatment_plan_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
