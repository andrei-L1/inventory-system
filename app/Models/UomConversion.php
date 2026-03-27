<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UomConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
    ];

    public function fromUom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_uom_id');
    }

    public function toUom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_uom_id');
    }
}
