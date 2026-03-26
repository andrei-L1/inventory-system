<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'run_by',
        'status',
        'file_path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the report definition this run belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the user who executed this report run.
     */
    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    /**
     * Check if the run was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }
}
