<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model that links the two transaction legs of a stock transfer.
 *
 * A transfer creates two Transaction records (an issue and a receipt).
 * This model provides the permanent, FK-enforced link between them
 * so the audit ledger is always coherent.
 *
 * @property int $id
 * @property int $outgoing_transaction_id
 * @property int $incoming_transaction_id
 * @property int $from_location_id
 * @property int $to_location_id
 * @property string|null $reference_number
 */
class Transfer extends Model
{
    protected $fillable = [
        'outgoing_transaction_id',
        'incoming_transaction_id',
        'from_location_id',
        'to_location_id',
        'reference_number',
    ];

    /** The issue-side transaction (stock leaves the origin). */
    public function outgoing(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'outgoing_transaction_id');
    }

    /** The receipt-side transaction (stock arrives at the destination). */
    public function incoming(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'incoming_transaction_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
