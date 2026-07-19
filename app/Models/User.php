<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_GENERAL_MANAGER = 'general_manager';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_ACCOUNTS = 'accounts';

    public const ROLE_WORKERS_MANAGER = 'workers_manager';

    public const ROLE_WORKER = 'worker';

    /** @var list<string> */
    public const STAFF_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GENERAL_MANAGER,
        self::ROLE_MANAGER,
        self::ROLE_ACCOUNTS,
        self::ROLE_WORKERS_MANAGER,
        self::ROLE_WORKER,
    ];

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_name',
        'email',
        'password',
        'role',
        'phone',
        'country',
        'profile_completed',
        'date_of_birth',
        'gender',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be appended to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Get the name for display (alias for customer_name for frontend compatibility).
     */
    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['customer_name'] ?? '');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'profile_completed' => 'boolean',
            'date_of_birth' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user's carts.
     */
    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get the user's rentals.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Get the user's active cart.
     */
    public function activeCart()
    {
        return $this->hasOne(Cart::class)->where('status', 'active');
    }

    /**
     * Get the user's invoices.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isGeneralManager(): bool
    {
        return $this->role === self::ROLE_GENERAL_MANAGER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isAccounts(): bool
    {
        return $this->role === self::ROLE_ACCOUNTS;
    }

    public function isWorkersManager(): bool
    {
        return $this->role === self::ROLE_WORKERS_MANAGER;
    }

    public function isWorker(): bool
    {
        return $this->role === self::ROLE_WORKER;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    /**
     * Staff users who may log into the dashboard panel.
     * Workers use /worker-app only.
     */
    public function canAccessDashboard(): bool
    {
        return $this->isStaff() && ! $this->isWorker();
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Named route for post-login / unauthorized redirects.
     */
    public function homeRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_WORKER => 'pwa.dashboard',
            self::ROLE_WORKERS_MANAGER => 'worker-orders.index',
            self::ROLE_ACCOUNTS => 'quotations.index',
            self::ROLE_MANAGER, self::ROLE_GENERAL_MANAGER, self::ROLE_ADMIN => 'dashboard',
            default => 'home',
        };
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'ادمن',
            self::ROLE_GENERAL_MANAGER => 'مدير عام',
            self::ROLE_MANAGER => 'مسئول',
            self::ROLE_ACCOUNTS => 'حسابات',
            self::ROLE_WORKERS_MANAGER => 'مدير العمال',
            self::ROLE_WORKER => 'عامل',
            default => 'عميل',
        };
    }
}
