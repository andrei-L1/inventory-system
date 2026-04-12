<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'sales_order_line_id',
        'product_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'discount_rate',
        'discount_amount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'unit_price' => 'decimal:8',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:8',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:8',
        'subtotal' => 'decimal:8',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
