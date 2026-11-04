<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/** A supporting document (delivery note, photo, approval) attached to a return or sample. */
class TransactionDocument extends Model
{
    /** Kept off the public disk: these are business records, served only after a permission check. */
    public const DISK = 'local';

    protected $fillable = ['path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected static function booted(): void
    {
        static::deleted(fn (TransactionDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
