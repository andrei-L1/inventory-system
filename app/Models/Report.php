<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'filters',
        'format',
        'created_by',
        'is_scheduled',
        'schedule_cron',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_scheduled' => 'boolean',
    ];

    /**
     * Get the user who created this report definition.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the historical runs for this report definition.
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }
}
