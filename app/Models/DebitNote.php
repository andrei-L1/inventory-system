<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitNote extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'DRAFT';
    const STATUS_POSTED = 'POSTED';
    const STATUS_APPLIED = 'APPLIED';
    const STATUS_VOID = 'VOID';

    protected $fillable = [
        'vendor_id',
        'purchase_order_id',
        'ref_transaction_id',
        'amount',
        'debit_note_number',
        'status',
        'notes',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
