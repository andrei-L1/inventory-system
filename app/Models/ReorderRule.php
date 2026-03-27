<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReorderRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'min_stock',
        'max_stock',
        'reorder_qty',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
