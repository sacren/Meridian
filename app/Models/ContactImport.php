<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\ContactImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One CSV contact import: the async status handle the upload endpoint creates
 * and the queued ImportContacts job drives. It records where the upload was
 * stored (disk + path), how the run settled (status + imported/failed counts),
 * and a per-row errors report naming which rows were skipped and why.
 */
#[Fillable([
    'campaign_id',
    'disk',
    'path',
    'status',
    'imported_count',
    'failed_count',
    'errors',
    'completed_at',
])]
class ContactImport extends Model
{
    /** @use HasFactory<ContactImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'errors' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The campaign this import loads contacts into.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
