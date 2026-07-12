<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCurrentFacility; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model;
#[Fillable(['facility_id','from_cashier_session_id','to_user_id','handed_over_cash','handed_over_documents','notes','handed_over_by','handed_over_at','received_by','received_at','status'])]
class CashierHandover extends Model { use BelongsToCurrentFacility, HasFactory; protected function casts(): array { return ['handed_over_cash'=>'decimal:2','handed_over_at'=>'datetime','received_at'=>'datetime']; } }
