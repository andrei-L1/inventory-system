<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPaymentRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_payment_id',
        'vendor_id',
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

    public function vendorPayment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
