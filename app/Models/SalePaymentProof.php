<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file backing a payment submission (MoMo screenshot, bank slip, receipt).
 * Stored on the private disk and only served through PaymentProofController.
 */
class SalePaymentProof extends Model
{
    protected $fillable = [
        'submission_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'submission_id' => 'integer',
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SalePaymentProof $proof) {
            Storage::disk($proof->disk)->delete($proof->path);
        });
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(SalePaymentSubmission::class, 'submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
