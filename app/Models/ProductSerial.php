<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSerial extends Model
{
    use HasFactory;

    // Status lifecycle: in_stock → sold | returned | damaged
    const STATUS_IN_STOCK = 'in_stock';
    const STATUS_SOLD     = 'sold';
    const STATUS_RETURNED = 'returned';
    const STATUS_DAMAGED  = 'damaged';

    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'current_location_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function transactionLines(): BelongsToMany
    {
        return $this->belongsToMany(TransactionLine::class, 'transaction_line_serials')
            ->withTimestamps()
            ->with(['transaction.type']);
    }
}
