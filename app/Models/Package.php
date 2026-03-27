<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'package_number',
        'weight',
        'length',
        'width',
        'height',
        'dimension_unit',
        'weight_unit',
        'contents_description',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
