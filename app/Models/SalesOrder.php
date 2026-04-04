<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'so_number',
        'customer_id',
        'status_id',
        'order_date',
        'requested_delivery_date',
        'total_amount',
        'currency',
        'notes',
        'carrier',
        'tracking_number',
        'created_by',
        'approved_by',
        'approved_at',
        'confirmed_at',
        'sent_at',
        'shipped_at',
        'delivered_at',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($so) {
            if (! $so->created_by && auth()->check()) {
                $so->created_by = auth()->id();
            }
        });
    }

    protected $casts = [
        'order_date' => 'date',
        'requested_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'sent_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function status()
    {
        return $this->belongsTo(SalesOrderStatus::class, 'status_id');
    }

    public function lines()
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function isDraft(): bool
    {
        return $this->status?->name === SalesOrderStatus::QUOTATION;
    }

    public function isConfirmed(): bool
    {
        return $this->status?->name === SalesOrderStatus::CONFIRMED;
    }

    public function canBeShipped(): bool
    {
        return in_array($this->status?->name, [
            SalesOrderStatus::CONFIRMED,
            SalesOrderStatus::PICKED,
            SalesOrderStatus::PACKED,
            SalesOrderStatus::PARTIALLY_SHIPPED
        ]);
    }
}
