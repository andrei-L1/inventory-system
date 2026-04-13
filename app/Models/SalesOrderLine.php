<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $appends = [
        'requirement_qty',
        'net_shipped_qty',
        'formatted_ordered_qty',
        'formatted_shipped_qty',
        'formatted_picked_qty',
        'formatted_packed_qty',
        'formatted_returned_qty',
        'formatted_remaining_qty',
        'invoiced_qty',
        'uninvoiced_qty',
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
        'tax_amount' => 'decimal:8',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:8',
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

    public function invoiceLines()
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * The original contracted quantity minus any formalized cancellations/credits.
     * This is the 'effective' target for fulfillment.
     */
    public function getRequirementQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub((string) $this->ordered_qty, (string) $this->credited_qty));
    }

    /**
     * Sum of all credits processed for this line.
     */
    public function getCreditedQtyAttribute(): string
    {
        // Credit lines have positive quantities in InvoiceLines usually, 
        // but we'll sum from Credit Notes.
        return (string) $this->invoiceLines()->whereHas('invoice', function($q) {
            $q->where('status', '!=', Invoice::STATUS_VOID)
              ->where('type', Invoice::TYPE_CREDIT_NOTE);
        })->sum('quantity');
    }

    /**
     * The actual physical stock state (Net Shipped).
     * Since the controller now decrements shipped_qty directly upon return,
     * this accessor simply returns the current counter value.
     */
    public function getNetShippedQtyAttribute(): string
    {
        return (string) $this->shipped_qty;
    }

    /**
     * Quantity to be picked (Requirement - Picked)
     */
    public function getRemainingPickQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub($this->requirement_qty, (string) $this->picked_qty));
    }

    /**
     * Quantity to be packed (Requirement capped by Picked - Packed)
     */
    public function getRemainingPackQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub((string) $this->picked_qty, (string) $this->packed_qty));
    }

    /**
     * Quantity to be shipped (Requirement capped by Packed - Shipped)
     */
    public function getRemainingShipQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub((string) $this->packed_qty, (string) $this->shipped_qty));
    }

    /**
     * Quantity that has already been billed on Invoices
     */
    public function getInvoicedQtyAttribute(): string
    {
        return (string) $this->invoiceLines()->with('invoice')->get()->reduce(function ($carry, $line) {
            // Only count if invoice exists (is not soft-deleted) and is not VOID
            if (! $line->invoice || $line->invoice->isVoid()) {
                return $carry;
            }

            return FinancialMath::add($carry, (string) $line->quantity);
        }, '0');
    }

    /**
     * Quantity that has been shipped but not yet invoiced
     */
    public function getUninvoicedQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub($this->net_shipped_qty, $this->invoiced_qty));
    }

    /**
     * Legacy accessor for total remaining to fulfill (Requirement - Shipped)
     */
    public function getRemainingQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub($this->requirement_qty, (string) $this->shipped_qty));
    }

    /**
     * Quantity that can be returned (Net Shipped)
     */
    public function getRemainingReturnQtyAttribute(): string
    {
        return $this->net_shipped_qty;
    }

    public function getFormattedOrderedQtyAttribute(): string
    {
        return UomHelper::format($this->requirement_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedShippedQtyAttribute(): string
    {
        return UomHelper::format($this->net_shipped_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
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
        $symbol = match ($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            default => '₱',
        };

        return $symbol.FinancialMath::format($this->unit_price, 2).' / '.($this->uom->abbreviation ?? 'pcs');
    }
}
