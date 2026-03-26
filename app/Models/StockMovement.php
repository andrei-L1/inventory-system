<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_movements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id',
        'location_id',
        'transaction_line_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'movement_date',
    ];

    /**
     * Disable updated_at since this is an immutable ledger.
     */
    public $timestamps = false;

    /**
     * Static boot for created_at.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
            if (! $model->movement_date) {
                $model->movement_date = $model->freshTimestamp();
            }
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'movement_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the product this movement belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the location this movement occurs at.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id')->withTrashed();
    }

    /**
     * Get the transaction line that generated this movement.
     */
    public function transactionLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class, 'transaction_line_id');
    }
}
