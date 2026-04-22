<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class BillLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'purchase_order_line_id',
        'transaction_line_id',
        'quantity',
        'unit_price',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'unit_price' => 'decimal:8',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:8',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:8',
        'subtotal' => 'decimal:8',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function transactionLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class);
    }

    /**
     * Get the product being billed via the PO line.
     */
    public function product(): HasOneThrough
    {
        return $this->hasOneThrough(
            Product::class,
            PurchaseOrderLine::class,
            'id', // Foreign key on purchase_order_lines table...
            'id', // Foreign key on products table...
            'purchase_order_line_id', // Local key on bill_lines table...
            'product_id' // Local key on purchase_order_lines table...
        );
    }
}
