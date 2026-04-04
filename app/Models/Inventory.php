<?php

namespace App\Models;

use App\Helpers\UomHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $location_id
 * @property float $quantity_on_hand
 * @property float $reserved_qty
 * @property float $average_cost
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Product $product
 * @property-read Location $location
 */
class Inventory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inventories';

    protected $fillable = [
        'product_id',
        'location_id',
        'quantity_on_hand',
        'reserved_qty',
        'average_cost',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:8',
        'reserved_qty' => 'decimal:8',
        'average_cost' => 'decimal:8',
    ];

    protected $appends = [
        'scaled_quantity_on_hand',
        'scaled_average_cost',
        'formatted_quantity_on_hand',
    ];

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * Get the scaled quantity on hand based on the product's UOM.
     */
    public function getScaledQuantityOnHandAttribute(): float
    {
        $multiplier = UomHelper::getMultiplierToSmallest($this->product->uom_id);

        return $multiplier > 0 ? (float) $this->getRawOriginal('quantity_on_hand') / $multiplier : (float) $this->getRawOriginal('quantity_on_hand');
    }

    /**
     * Get the scaled average cost based on the product's UOM.
     */
    public function getScaledAverageCostAttribute(): float
    {
        $multiplier = UomHelper::getMultiplierToSmallest($this->product->uom_id);

        return $multiplier > 0 ? (float) $this->getRawOriginal('average_cost') * $multiplier : (float) $this->getRawOriginal('average_cost');
    }

    /**
     * Get the scaled reserved quantity based on the product's UOM.
     */
    public function getScaledReservedQtyAttribute(): float
    {
        $multiplier = UomHelper::getMultiplierToSmallest($this->product->uom_id);

        return $multiplier > 0 ? (float) $this->getRawOriginal('reserved_qty') / $multiplier : (float) $this->getRawOriginal('reserved_qty');
    }

    /**
     * Get the formatted quantity on hand.
     */
    public function getFormattedQuantityOnHandAttribute(): string
    {
        return UomHelper::format($this->scaled_quantity_on_hand, $this->product->uom_id);
    }
}
