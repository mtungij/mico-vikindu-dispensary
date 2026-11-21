<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PncVisit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'delivery_date' => 'date',
            'follow_up_date' => 'date',
            'maternal_vitals' => 'array',
        ];
    }

    public function rchEncounter(): BelongsTo
    {
        return $this->belongsTo(RchEncounter::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }
}
