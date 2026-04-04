<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'location_id',
        'uom_id',
        'ordered_qty',
        'shipped_qty',
        'picked_qty',
        'packed_qty',
        'returned_qty',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'discount_rate',
        'discount_amount',
        'notes',
        'subtotal',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:8',
        'shipped_qty' => 'decimal:8',
        'picked_qty' => 'decimal:8',
        'packed_qty' => 'decimal:8',
        'returned_qty' => 'decimal:8',
        'unit_price' => 'decimal:8',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:6',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:6',
        'subtotal' => 'decimal:8',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get the remaining quantity to be picked/shipped.
     */
    public function getRemainingQtyAttribute(): float
    {
        return max(0, (float) $this->ordered_qty - (float) $this->shipped_qty);
    }
}
