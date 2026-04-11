<?php

namespace App\Models;

use App\Helpers\UomHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $appends = [
        'formatted_ordered_qty',
        'formatted_shipped_qty',
        'formatted_picked_qty',
        'formatted_packed_qty',
        'formatted_returned_qty',
        'formatted_remaining_qty',
    ];

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
     * Quantity to be picked (Ordered - Picked)
     */
    public function getRemainingPickQtyAttribute(): float
    {
        return max(0, (float) $this->ordered_qty - (float) $this->picked_qty);
    }

    /**
     * Quantity to be packed (Picked - Packed)
     */
    public function getRemainingPackQtyAttribute(): float
    {
        return max(0, (float) $this->picked_qty - (float) $this->packed_qty);
    }

    /**
     * Quantity to be shipped (Packed - Shipped)
     */
    public function getRemainingShipQtyAttribute(): float
    {
        return max(0, (float) $this->packed_qty - (float) $this->shipped_qty);
    }

    /**
     * Legacy accessor for total remaining to ship (Ordered - Shipped)
     */
    public function getRemainingQtyAttribute(): float
    {
        return max(0, (float) $this->ordered_qty - (float) $this->shipped_qty);
    }

    /**
     * Quantity that can be returned (Shipped - Returned)
     */
    public function getRemainingReturnQtyAttribute(): float
    {
        return max(0, (float) $this->shipped_qty - (float) $this->returned_qty);
    }

    public function getFormattedOrderedQtyAttribute(): string
    {
        return UomHelper::format($this->ordered_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedShippedQtyAttribute(): string
    {
        return UomHelper::format($this->shipped_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedPickedQtyAttribute(): string
    {
        return UomHelper::format($this->picked_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedPackedQtyAttribute(): string
    {
        return UomHelper::format($this->packed_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedReturnedQtyAttribute(): string
    {
        return UomHelper::format($this->returned_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedRemainingQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedRemainingPickQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_pick_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedRemainingPackQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_pack_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedRemainingShipQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_ship_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedRemainingReturnQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_return_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        // S-L2: Use the parent SO's currency code dynamically instead of hardcoding '₱'.
        // Falls back to PHP if the relationship is not loaded to prevent N+1 errors.
        $currencyCode = $this->salesOrder?->currency ?? 'PHP';
        $symbol = match($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            default => '₱',
        };

        return $symbol.number_format($this->unit_price, 2).' / '.($this->uom->abbreviation ?? 'pcs');
    }
}
