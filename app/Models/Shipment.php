<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_number',
        'transaction_id',
        'sales_order_id',
        'carrier_id',
        'tracking_number',
        'status',
        'shipped_at',
        'delivered_at',
        'shipping_cost',
        'notes',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }
}
