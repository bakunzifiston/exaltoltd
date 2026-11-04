<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SALES = 'sales';
    public const ROLE_PRODUCTION = 'production';

    /** Human labels for every role, in privilege order. */
    public const ROLES = [
        self::ROLE_OWNER => 'Owner',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_SALES => 'Sales / Cashier',
        self::ROLE_PRODUCTION => 'Production',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'can_approve_payments',
        'phone',
        'employee_id',
        'sales_target',
        'commission_rate',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Sensible defaults so a freshly built model (before reload) behaves correctly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_SALES,
        'is_active' => true,
        'can_approve_payments' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'can_approve_payments' => 'boolean',
            'sales_target' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'password' => 'hashed',
        ];
    }

    // --- Role helpers -------------------------------------------------------

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isProduction(): bool
    {
        return $this->role === self::ROLE_PRODUCTION;
    }

    /** Owner or Manager — the roles that see the whole business. */
    public function isAdministrator(): bool
    {
        return $this->hasRole(self::ROLE_OWNER, self::ROLE_MANAGER);
    }

    /** May confirm that a reported payment was received. Mirrors the `approve-payments` gate. */
    public function canApprovePayments(): bool
    {
        return $this->is_active && ($this->isOwner() || $this->can_approve_payments);
    }

    /** Everyone who should be asked to review a reported payment. */
    public function scopePaymentApprovers(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('role', self::ROLE_OWNER)->orWhere('can_approve_payments', true));
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    /** Where this user should land after logging in. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SALES => 'admin.sales.index',
            self::ROLE_PRODUCTION => 'admin.productions.index',
            default => 'admin.dashboard',
        };
    }

    // --- Relationships ------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class, 'created_by');
    }

    public function recordedPayments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'recorded_by');
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(SalePaymentSubmission::class, 'submitted_by');
    }
}
