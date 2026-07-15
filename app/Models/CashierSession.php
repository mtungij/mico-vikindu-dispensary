<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','user_id','session_number','shift','opened_at','opening_float','cash_drawer','status','closed_at','expected_cash','declared_cash','variance','notes','opened_by','closed_by','approved_by','approved_at'])]
class CashierSession extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['opened_at'=>'datetime','closed_at'=>'datetime','approved_at'=>'datetime','opening_float'=>'decimal:2','expected_cash'=>'decimal:2','declared_cash'=>'decimal:2','variance'=>'decimal:2']; }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
