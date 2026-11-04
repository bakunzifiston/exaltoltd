<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment someone reports as received, waiting for an approver to confirm it.
 * Only an approved submission writes a SalePayment, so the ledger stays "money confirmed".
 */
class SalePaymentSubmission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** Approved, then its ledger payment was removed. */
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Awaiting approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_WITHDRAWN => 'Withdrawn',
        self::STATUS_REVERSED => 'Reversed',
    ];

    /** Methods a customer can pay by without leaving a document; proof is optional for these. */
    public const PROOF_OPTIONAL_METHODS = ['Cash'];

    /** Methods that always produce a transaction reference worth checking. */
    public const REFERENCE_REQUIRED_METHODS = ['MoMo', 'Bank Transfer'];

    public const MAX_PROOFS = 3;

    public const PROOF_MAX_KB = 5120;

    public const PROOF_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    protected $fillable = [
        'sale_id',
        'amount',
        'method',
        'paid_at',
        'reference',
        'notes',
        'status',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'cash_account_id',
        'sale_payment_id',
    ];

    // Integer ids so the self-approval check (===) holds on every database driver.
    protected $casts = [
        'sale_id' => 'integer',
        'submitted_by' => 'integer',
        'reviewed_by' => 'integer',
        'cash_account_id' => 'integer',
        'sale_payment_id' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public static function proofRequiredFor(?string $method): bool
    {
        return ! in_array($method, self::PROOF_OPTIONAL_METHODS, true);
    }

    public static function referenceRequiredFor(?string $method): bool
    {
        return in_array($method, self::REFERENCE_REQUIRED_METHODS, true);
    }

    /**
     * Validation rules for a payment's proof files, shared by every form that takes a payment.
     *
     * @return array<string, list<string>>
     */
    public static function proofRules(string $field, bool $required): array
    {
        return [
            $field => [$required ? 'required' : 'nullable', 'array', 'max:'.self::MAX_PROOFS],
            $field.'.*' => ['file', 'mimes:'.implode(',', self::PROOF_MIMES), 'max:'.self::PROOF_MAX_KB],
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class, 'sale_payment_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(SalePaymentProof::class, 'submission_id');
    }
}
