<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplenishmentSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'current_stock',
        'suggested_qty',
        'reason',
        'status',
        'purchase_order_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
