<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderStatus extends Model
{
    use HasFactory;

    public const QUOTATION = 'quotation';

    public const QUOTATION_SENT = 'quotation_sent';

    public const CONFIRMED = 'confirmed';

    public const PICKED = 'picked';

    public const PARTIALLY_PICKED = 'partially_picked';

    public const PACKED = 'packed';

    public const PARTIALLY_PACKED = 'partially_packed';

    public const SHIPPED = 'shipped';

    public const PARTIALLY_SHIPPED = 'partially_shipped';

    public const CANCELLED = 'cancelled';

    public const CLOSED = 'closed';

    protected $fillable = ['name', 'is_editable'];

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'status_id');
    }
}
