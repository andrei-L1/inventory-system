<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use App\Helpers\UomHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $product_id
 * @property int|null $location_id
 * @property float $quantity
 * @property float $unit_cost
 * @property float $total_cost
 * @property float $unit_price
 * @property int|null $costing_method_id
 * @property int|null $uom_id
 * @property string|null $notes
 * @property-read Transaction $transaction
 * @property-read Product $product
 * @property-read Location|null $location
 * @property-read UnitOfMeasure|null $uom
 */
class TransactionLine extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'location_id',
        'uom_id',
        'base_uom_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'unit_price',
        'costing_method_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'unit_cost' => 'decimal:8',
        'total_cost' => 'decimal:8',
        'unit_price' => 'decimal:8',
    ];

    /**
     * Get the UOM used for this transaction line.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * Get the costing method for this transaction line.
     */
    public function costingMethod(): BelongsTo
    {
        return $this->belongsTo(CostingMethod::class);
    }

    /**
     * Get the transaction header.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the location.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * Get the serial numbers associated with this transaction line.
     */
    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(ProductSerial::class, 'transaction_line_serials');
    }

    /**
     * Get the bill lines associated with this transaction line.
     */
    public function billLines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    /**
     * Get the total quantity already billed for this receipt line.
     * EXCLUDES lines from VOIDed bills to allow for correct re-billing.
     */
    public function getBilledQtyAttribute(): string
    {
        // Use sum() on the relationship to handle filtering efficiently
        // if billLines is not loaded. If loaded, this might cause a query.
        // We'll use a collection sum if it's already loaded for performance.
        $lines = $this->relationLoaded('billLines') 
            ? $this->billLines 
            : $this->billLines()->with('bill')->get();

        $total = '0';
        foreach ($lines as $line) {
            if ($line->bill && $line->bill->status !== Bill::STATUS_VOID) {
                $total = FinancialMath::add($total, (string) $line->quantity);
            }
        }

        return $total;
    }

    /**
     * Get the remaining quantity available to be billed for this receipt line.
     */
    public function getBillableQtyAttribute(): string
    {
        return FinancialMath::max('0', FinancialMath::sub((string) $this->quantity, $this->billed_qty));
    }

    /**
     * Get the base UOM (pieces) used for storage.
     */
    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    /**
     * Get the formatted quantity using the original UOM.
     */
    public function getFormattedQuantityAttribute(): string
    {
        $uomId = $this->uom_id ?? $this->product->uom_id;
        $multiplierStr = (string) UomHelper::getMultiplierToSmallest($uomId, $this->product_id, false);
        $scaledQty = FinancialMath::isPositive($multiplierStr) ? FinancialMath::div((string) $this->quantity, $multiplierStr) : (string) $this->quantity;

        return UomHelper::format($scaledQty, $uomId, $this->product_id, false);
    }

    public function getFormattedUnitCostAttribute(): ?string
    {
        if ($this->unit_cost === null) {
            return null;
        }
        $uomId = $this->uom_id ?? $this->product->uom_id;
        $multiplierStr = (string) UomHelper::getMultiplierToSmallest($uomId, $this->product_id, false);
        $scaledCost = FinancialMath::isPositive($multiplierStr) ? FinancialMath::mul((string) $this->unit_cost, $multiplierStr) : (string) $this->unit_cost;

        $symbol = '₱';

        return $symbol.FinancialMath::format($scaledCost, 2).' / '.($this->uom->abbreviation ?? 'pcs');
    }

    public function getFormattedUnitCost8dpAttribute(): ?string
    {
        if ($this->unit_cost === null) {
            return null;
        }
        $uomId = $this->uom_id ?? $this->product->uom_id;
        $multiplierStr = (string) UomHelper::getMultiplierToSmallest($uomId, $this->product_id, false);
        $scaledCost = FinancialMath::isPositive($multiplierStr) ? FinancialMath::mul((string) $this->unit_cost, $multiplierStr) : (string) $this->unit_cost;

        $symbol = '₱';

        return $symbol.FinancialMath::format($scaledCost, 8).' / '.($this->uom->abbreviation ?? 'pcs');
    }

    public function getFormattedUnitPriceAttribute(): ?string
    {
        if ($this->unit_price === null) {
            return null;
        }
        $uomId = $this->uom_id ?? $this->product->uom_id;
        $multiplierStr = (string) UomHelper::getMultiplierToSmallest($uomId, $this->product_id, false);
        $scaledPrice = FinancialMath::isPositive($multiplierStr) ? FinancialMath::mul((string) $this->unit_price, $multiplierStr) : (string) $this->unit_price;

        $symbol = '₱';

        return $symbol.FinancialMath::format($scaledPrice, 2).' / '.($this->uom->abbreviation ?? 'pcs');
    }

    public function getFormattedUnitPrice8dpAttribute(): ?string
    {
        if ($this->unit_price === null) {
            return null;
        }
        $uomId = $this->uom_id ?? $this->product->uom_id;
        $multiplierStr = (string) UomHelper::getMultiplierToSmallest($uomId, $this->product_id, false);
        $scaledPrice = FinancialMath::isPositive($multiplierStr) ? FinancialMath::mul((string) $this->unit_price, $multiplierStr) : (string) $this->unit_price;

        $symbol = '₱';

        return $symbol.FinancialMath::format($scaledPrice, 8).' / '.($this->uom->abbreviation ?? 'pcs');
    }
}
