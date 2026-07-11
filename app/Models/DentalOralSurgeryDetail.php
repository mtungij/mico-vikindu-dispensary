<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dental_procedure_id','flap_raised','bone_removed','tooth_sectioned','socket_debrided','hemostasis_achieved','sutures_used','suture_material','number_of_sutures','specimen_sent','specimen_reference','complications','post_op_condition'])]
class DentalOralSurgeryDetail extends Model
{
    use HasFactory;
    protected function casts(): array { return ['flap_raised'=>'boolean','bone_removed'=>'boolean','tooth_sectioned'=>'boolean','socket_debrided'=>'boolean','hemostasis_achieved'=>'boolean','sutures_used'=>'boolean','specimen_sent'=>'boolean']; }
    public function procedure(): BelongsTo { return $this->belongsTo(DentalProcedure::class, 'dental_procedure_id'); }
}
