<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'quantity',
        'unit_cost',
        'total_cost',
        'unit_price',
        'costing_method_id',
        'notes',
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
    public function serials()
    {
        return $this->belongsToMany(ProductSerial::class, 'transaction_line_serials');
    }
}
