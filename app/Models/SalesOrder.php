<?php

namespace App\Models;

use App\Helpers\FinancialMath;
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
        // 'expected_shipping_date' is handled via accessor/mutator below
        // and maps to the 'requested_delivery_date' DB column.
        'expected_shipping_date',
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

    /**
     * Accessor: expose `requested_delivery_date` under the app-layer name
     * `expected_shipping_date` that the controller, resource, and frontend use.
     */
    public function getExpectedShippingDateAttribute(): ?string
    {
        return $this->requested_delivery_date
            ? $this->requested_delivery_date->toDateString()
            : null;
    }

    /**
     * Mutator: writing to `expected_shipping_date` persists to the real DB column.
     */
    public function setExpectedShippingDateAttribute(?string $value): void
    {
        $this->attributes['requested_delivery_date'] = $value;
    }

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

    public function canBePicked(): bool
    {
        return in_array($this->status?->name, [
            SalesOrderStatus::CONFIRMED,
            SalesOrderStatus::PARTIALLY_PICKED,
            SalesOrderStatus::PICKED,
            SalesOrderStatus::PARTIALLY_PACKED,
            SalesOrderStatus::PACKED,
            SalesOrderStatus::PARTIALLY_SHIPPED,
        ]);
    }

    public function canBePacked(): bool
    {
        return in_array($this->status?->name, [
            SalesOrderStatus::PARTIALLY_PICKED,
            SalesOrderStatus::PICKED,
            SalesOrderStatus::PARTIALLY_PACKED,
            SalesOrderStatus::PACKED,
            SalesOrderStatus::PARTIALLY_SHIPPED,
        ]);
    }

    public function canBeShipped(): bool
    {
        return in_array($this->status?->name, [
            SalesOrderStatus::CONFIRMED,
            SalesOrderStatus::PARTIALLY_PICKED,
            SalesOrderStatus::PICKED,
            SalesOrderStatus::PARTIALLY_PACKED,
            SalesOrderStatus::PACKED,
            SalesOrderStatus::PARTIALLY_SHIPPED,
        ]);
    }

    /**
     * Recalculate and persist the SO status from current line quantities.
     *
     * This is the bidirectional status engine used after returns. Unlike the
     * forward-only updates in pick/pack/ship, this method inspects actual
     * quantities to determine the correct state, allowing the status to
     * move backwards (e.g. SHIPPED → PARTIALLY_SHIPPED) when items are returned.
     */
    public function recalculateStatus(): void
    {
        $this->loadMissing('lines');
        $lines = $this->lines;

        if ($lines->isEmpty()) {
            return;
        }

        // Accumulate quantities in BCMath strings — no float summation, no epsilon.
        $totalOrdered = '0';
        $totalShipped = '0';
        $totalPacked = '0';
        $totalPicked = '0';

        foreach ($lines as $l) {
            $totalOrdered = FinancialMath::add($totalOrdered, (string) $l->ordered_qty);
            $totalShipped = FinancialMath::add($totalShipped, (string) $l->shipped_qty);
            $totalPacked = FinancialMath::add($totalPacked, (string) $l->packed_qty);
            $totalPicked = FinancialMath::add($totalPicked, (string) $l->picked_qty);
        }

        // Walk the fulfillment hierarchy top-down.
        // FinancialMath::gte/gt use cmp() at scale=0 — no epsilon needed.
        if (FinancialMath::gte($totalShipped, $totalOrdered)) {
            $statusName = SalesOrderStatus::SHIPPED;
        } elseif (FinancialMath::isPositive($totalShipped)) {
            $statusName = SalesOrderStatus::PARTIALLY_SHIPPED;
        } elseif (FinancialMath::gte($totalPacked, $totalOrdered)) {
            $statusName = SalesOrderStatus::PACKED;
        } elseif (FinancialMath::isPositive($totalPacked)) {
            $statusName = SalesOrderStatus::PARTIALLY_PACKED;
        } elseif (FinancialMath::gte($totalPicked, $totalOrdered)) {
            $statusName = SalesOrderStatus::PICKED;
        } elseif (FinancialMath::isPositive($totalPicked)) {
            $statusName = SalesOrderStatus::PARTIALLY_PICKED;
        } else {
            // All progress reversed — order is back to confirmed/ready state.
            $statusName = SalesOrderStatus::CONFIRMED;
        }

        $status = SalesOrderStatus::where('name', $statusName)->firstOrFail();
        $this->update(['status_id' => $status->id]);
        $this->setRelation('status', $status);

        // --- BACKORDER AUTOMATION (PHASE 5.6) ---
        // Any time an SO recalculates its fulfillment status (whether backward or forward),
        // ping the Replenishment Engine to ensure we aren't short.
        if (app()->bound(\App\Services\Procurement\ReplenishmentService::class)) {
            app(\App\Services\Procurement\ReplenishmentService::class)->generateSuggestions();
        } else {
            // Fallback for when bound via fresh app instance in tinker/tests
            (new \App\Services\Procurement\ReplenishmentService)->generateSuggestions();
        }
    }
}
