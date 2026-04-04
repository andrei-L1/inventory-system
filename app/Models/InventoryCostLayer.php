<?php

namespace App\Models;

use App\Helpers\UomHelper;
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
        'received_qty' => 'decimal:8',
        'issued_qty' => 'decimal:8',
        'remaining_qty' => 'decimal:8',
        'unit_cost' => 'decimal:8',
        'receipt_date' => 'date',
        'is_exhausted' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['remaining_qty'];

    /**
     * Calculate current remaining quantity on-the-fly.
     */
    public function getRemainingQtyAttribute(): float
    {
        return (float) ($this->received_qty - $this->issued_qty);
    }

    /**
     * Get the product associated with this layer.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the formatted remaining quantity.
     */
    public function getFormattedRemainingQtyAttribute(): string
    {
        return UomHelper::format($this->remaining_qty, $this->product->uom_id);
    }

    /**
     * Get the formatted received quantity.
     */
    public function getFormattedReceivedQtyAttribute(): string
    {
        return UomHelper::format($this->received_qty, $this->product->uom_id);
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
