<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'name',
        'abbreviation',
        'is_active',
    ];
}
