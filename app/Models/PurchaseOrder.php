<?php

namespace App\Models;

use App\Helpers\FinancialMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'status_id',
        'order_date',
        'expected_delivery_date',
        'total_amount',
        'currency',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'sent_at',
        'shipped_at',
        'carrier',
        'tracking_number',
        'billing_status',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($po) {
            if (! $po->created_by && auth()->check()) {
                $po->created_by = auth()->id();
            }
        });
    }

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'shipped_at' => 'datetime',
        'total_amount' => 'decimal:2', // DB column is decimal(18,2); GAAP compliant header total
    ];

    /**
     * Get the vendor this PO is issued to.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the status of this PO.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderStatus::class, 'status_id');
    }

    /**
     * Get the line items for this PO.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Get the inventory transactions (receipts) related to this PO.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the landed costs (freight, duty, insurance, etc.) for this PO.
     */
    public function landedCosts(): HasMany
    {
        return $this->hasMany(LandedCost::class);
    }

    /**
     * Get the user who created this PO.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this PO.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Synchronize and persist the formal billing status to the database.
     * Logic: Compares TOTAL Billed Qty vs TOTAL Received Qty.
     */
    public function syncBillingStatus(): void
    {
        $this->loadMissing('lines');

        $totalReceived = '0';
        $totalBilled = '0';

        foreach ($this->lines as $line) {
            $totalReceived = FinancialMath::add($totalReceived, $line->net_received_qty);
            $totalBilled = FinancialMath::add($totalBilled, (string) $line->billed_qty);
        }

        if (FinancialMath::isPositive($totalBilled)) {
            $status = FinancialMath::gte($totalBilled, $totalReceived) ? 'BILLED' : 'PARTIALLY_BILLED';
        } else {
            $status = 'UNBILLED';
        }

        $this->update(['billing_status' => $status]);
    }

    /**
     * Check if the PO is fully received.
     */
    public function isCompleted(): bool
    {
        // Re-load lines to ensure we are calculating against fresh ledger data
        $this->loadMissing('lines');

        if ($this->lines->isEmpty()) {
            return false;
        }

        return $this->lines->every(function ($line) {
            return FinancialMath::gte($line->net_received_qty, $line->requirement_qty);
        });
    }

    /**
     * Determine and persist the correct status based on fulfillment progress.
     */
    public function recalculateStatus(): void
    {
        $this->loadMissing('lines');

        if (in_array($this->status?->name, ['draft', 'cancelled'])) {
            return;
        }

        $isCompleted = $this->isCompleted();
        $totalReceived = '0';
        foreach ($this->lines as $l) {
            $totalReceived = FinancialMath::add($totalReceived, $l->net_received_qty);
        }

        $statusName = 'closed';
        if (! $isCompleted) {
            $statusName = FinancialMath::isPositive($totalReceived) ? 'partially_received' : 'sent';

            // If it was in transit, keep it in transit unless it's received
            if ($this->status?->name === 'in_transit' && FinancialMath::isZero($totalReceived)) {
                $statusName = 'in_transit';
            }
        }

        $newStatus = PurchaseOrderStatus::where('name', $statusName)->first();
        if ($newStatus && $this->status_id !== $newStatus->id) {
            $this->update(['status_id' => $newStatus->id]);
        }
    }

    /**
     * Determine if the PO is in a state where it can be formally cancelled.
     * Orders with existing receipts should be 'closed' instead.
     */
    public function getCanBeCancelledAttribute(): bool
    {
        if (in_array($this->status?->name, ['draft', 'cancelled', 'closed'])) {
            return false;
        }

        // Must not have any received quantity across all lines
        return $this->lines->every(fn ($l) => FinancialMath::isZero((string) $l->received_qty));
    }
}
