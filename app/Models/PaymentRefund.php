<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id',
        'customer_id',
        'amount',
        'refund_number',
        'refund_date',
        'refund_method',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'refund_date' => 'date:Y-m-d',
        'amount' => 'decimal:8',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
