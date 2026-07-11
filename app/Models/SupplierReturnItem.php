<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['supplier_return_id', 'medicine_id', 'medicine_batch_id', 'quantity', 'unit_cost', 'total_cost', 'reason'])]
class SupplierReturnItem extends Model
{
    use HasFactory;
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2']; }
}
