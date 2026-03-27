<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'currency', 'is_active'];

    public function items()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
