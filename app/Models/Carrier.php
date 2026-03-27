<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'tracking_url_template',
        'is_active',
    ];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
