<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','benefit_package_id','membership_id','patient_id','visit_id','invoice_id','claim_number','external_claim_number','claim_type','service_date_from','service_date_to','status','currency','gross_amount','patient_amount','payer_claimed_amount','approved_amount','rejected_amount','paid_amount','outstanding_amount','diagnosis_summary','primary_diagnosis_code','authorization_id','referral_id','prepared_by','prepared_at','validated_by','validated_at','submitted_by','submitted_at','approved_at','rejected_at','paid_at','rejection_reason_id','rejection_notes','correction_reason','resubmission_count','version','parent_claim_id','batch_id','notes','created_by','updated_by'])]
class InsuranceClaim extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['service_date_from'=>'date','service_date_to'=>'date','gross_amount'=>'decimal:2','patient_amount'=>'decimal:2','payer_claimed_amount'=>'decimal:2','approved_amount'=>'decimal:2','rejected_amount'=>'decimal:2','paid_amount'=>'decimal:2','outstanding_amount'=>'decimal:2','prepared_at'=>'datetime','validated_at'=>'datetime','submitted_at'=>'datetime','approved_at'=>'datetime','rejected_at'=>'datetime','paid_at'=>'datetime']; }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function scheme(): BelongsTo { return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id'); }
    public function membership(): BelongsTo { return $this->belongsTo(PatientInsuranceMembership::class, 'membership_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function items(): HasMany { return $this->hasMany(InsuranceClaimItem::class); }
    public function attachments(): HasMany { return $this->hasMany(InsuranceClaimAttachment::class); }
    public function notes(): HasMany { return $this->hasMany(InsuranceClaimNote::class); }
    public function batch(): BelongsTo { return $this->belongsTo(InsuranceClaimBatch::class, 'batch_id'); }
    public function isImmutable(): bool { return in_array($this->status, ['batched','submitted','received','under_review','approved','partially_approved','paid','partially_paid','closed','resubmitted'], true); }
}
