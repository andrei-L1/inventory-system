<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderStatus extends Model
{
    protected $fillable = ['name', 'is_editable'];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'status_id');
    }
}
