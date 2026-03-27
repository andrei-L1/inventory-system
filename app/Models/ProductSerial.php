<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerial extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'current_location_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function currentLocation()
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function transactionLines()
    {
        return $this->belongsToMany(TransactionLine::class, 'transaction_line_serials');
    }
}
