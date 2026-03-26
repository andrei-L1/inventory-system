<?php

namespace App\Traits;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    /**
     * Get all of the attachments for the model.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get attachments in a specific collection.
     */
    public function attachmentsIn(string $collection): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
            ->where('collection_name', $collection);
    }
}
