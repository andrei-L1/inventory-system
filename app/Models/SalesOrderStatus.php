<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_editable'];

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'status_id');
    }
}
