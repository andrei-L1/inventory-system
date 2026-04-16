<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'uom_id',
        'ordered_qty',
        'received_qty',
        'returned_qty',
        'unit_cost',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'notes',
    ];

    protected $casts = [
        'ordered_qty'      => 'decimal:8',
        'received_qty'     => 'decimal:8',
        'returned_qty'     => 'decimal:8',
        'unit_cost'        => 'decimal:8',
        'discount_rate'    => 'decimal:2',
        'discount_amount'  => 'decimal:8',
        'tax_rate'         => 'decimal:2',
        'tax_amount'       => 'decimal:8',
        'total_cost'       => 'decimal:8', // Virtual column from DB
    ];

    protected $appends = [
        'billed_qty',
        'billable_qty',
        'requirement_qty',
        'net_received_qty',
        'formatted_ordered_qty',
        'formatted_received_qty',
        'formatted_returned_qty',
        'formatted_pending_qty',
    ];

    /**
     * Get the product being ordered.
     * Including trashed allows historical POs to show product info.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the UOM used for this specific order line.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get the remaining quantity to be received against the net requirement.
     */
    public function getRemainingQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub((string) $this->requirement_qty, (string) $this->net_received_qty));
    }

    /**
     * The original contracted quantity minus any formalized cancellations/credits.
     * This is the 'effective' target for fulfillment.
     */
    public function getRequirementQtyAttribute(): string
    {
        // requirement = ordered_qty + (sum of negative bill lines)
        // Since credited_qty is returned as a positive absolute number from our logic below
        return FinancialMath::max('0', FinancialMath::sub((string) $this->ordered_qty, $this->credited_qty));
    }

    /**
     * Sum of all returns processed for this line (Absolute value).
     * Used to calculate the net requirement and net receipt.
     */
    public function getCreditedQtyAttribute(): string
    {
        $multiplier = (string) UomHelper::getMultiplierToSmallest($this->uom_id, $this->product_id);
        
        // Sum negative quantities from posted Debit Notes
        $totalPieces = (string) abs($this->billLines()->whereHas('bill', function ($q) {
            $q->where('status', '!=', Bill::STATUS_VOID)
              ->where('type', Bill::TYPE_DEBIT_NOTE);
        })->where('quantity', '<', 0)->sum('quantity'));

        return FinancialMath::div($totalPieces, $multiplier);
    }

    /**
     * The actual stock currently held against this PO line (Net Received).
     * Since the controller now decrements received_qty directly upon return,
     * this accessor simply returns the current counter value.
     */
    public function getNetReceivedQtyAttribute(): string
    {
        return (string) $this->received_qty;
    }

    /**
     * Get the PO this line belongs to.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the bill lines linked to this PO line.
     */
    public function billLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    /**
     * DYNAMIC ACCESSOR: Calculate billed quantity from the master ledger.
     * Logic: Sum(BillLine.quantity) where Bill.status != VOID.
     * 
     * NOTE: BillLine stores quantities in ATOMIC PIECES.
     * We must scale back to the ordering UOM for these calculations.
     */
    public function getBilledQtyAttribute(): string
    {
        // 1. Fetch multiplier to convert Atomic Pieces -> PO UOM units
        $multiplier = (string) UomHelper::getMultiplierToSmallest($this->uom_id, $this->product_id);

        // 2. Aggregate quantity from all non-voided bill documents
        $totalPieces = (string) $this->billLines()->whereHas('bill', function ($q) {
            $q->where('status', '!=', Bill::STATUS_VOID);
        })->sum('quantity');

        // 3. Return scaled value (Pieces / Multiplier)
        return FinancialMath::div($totalPieces, $multiplier);
    }

    /**
     * Quantity that has been received but not yet formally billed.
     * Calculated as: received_qty - billed_qty (Dynamic).
     */
    public function getBillableQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub((string) $this->received_qty, $this->billed_qty));
    }

    public function getFormattedOrderedQtyAttribute(): string
    {
        return UomHelper::format($this->requirement_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedReceivedQtyAttribute(): string
    {
        return UomHelper::format($this->net_received_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedReturnedQtyAttribute(): string
    {
        return UomHelper::format($this->returned_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedPendingQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_qty, $this->uom_id ?? $this->product->uom_id, $this->product_id);
    }

    public function getFormattedUnitCostAttribute(): string
    {
        $currency = $this->purchaseOrder->currency ?? 'USD';

        return $currency.' '.FinancialMath::format($this->unit_cost, 2).' / '.($this->uom->abbreviation ?? 'pcs');
    }
}
