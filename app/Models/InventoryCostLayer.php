<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostLayer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id',
        'location_id',
        'transaction_line_id',
        'batch_number',
        'expiry_date',
        'received_qty',
        'issued_qty',
        'unit_cost',
        'receipt_date',
        'is_exhausted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'received_qty' => 'float',
        'issued_qty' => 'float',
        'remaining_qty' => 'float',
        'unit_cost' => 'float',
        'receipt_date' => 'date',
        'is_exhausted' => 'boolean',
    ];

    /**
     * Get the product associated with this layer.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the location for this layer.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * Get the transaction line that created this layer.
     */
    public function transactionLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class, 'transaction_line_id');
    }
}
