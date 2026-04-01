<?php

namespace App\Models;

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
}
