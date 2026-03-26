<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionLine extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'location_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'unit_price',
        'costing_method',
        'notes',
    ];

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
     * Get the resulting stock movements.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'transaction_line_id');
    }
}
