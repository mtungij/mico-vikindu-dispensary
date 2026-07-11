<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BedsideProcedure;
use App\Models\ObservationAdmission;
use App\Models\Service;

class BedsideProcedureService { public function __construct(private readonly ObservationBillingService $billing) {} public function record(ObservationAdmission $a, array $data, $actor): BedsideProcedure { $invoiceItem = ! empty($data['service_id']) ? $this->billing->addProcedureCharge($a, Service::query()->where('facility_id',$a->facility_id)->findOrFail($data['service_id']), $actor) : null; $p = BedsideProcedure::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'visit_id'=>$a->visit_id,'observation_order_id'=>$data['observation_order_id'] ?? null,'service_id'=>$data['service_id'] ?? null,'procedure_name_snapshot'=>$data['procedure_name_snapshot'],'performed_by'=>$actor->id,'assisted_by'=>$data['assisted_by'] ?? null,'performed_at'=>$data['performed_at'] ?? now(),'status'=>$data['status'] ?? 'completed','findings'=>$data['findings'] ?? null,'materials_used'=>$data['materials_used'] ?? null,'complications'=>$data['complications'] ?? null,'notes'=>$data['notes'] ?? null,'invoice_item_id'=>$invoiceItem?->id,'created_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'bedside_procedure_completed','subject_type'=>$p::class,'subject_id'=>$p->id]); return $p; } }
