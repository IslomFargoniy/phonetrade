<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $phone
 * @property Role $role
 * @property bool $is_active
 * @property bool $can_refund
 * @property int|null $telegram_chat_id
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'phone', 'password', 'role', 'is_active', 'can_refund'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'can_refund' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Superadmin hech bir do'konga tegishli emas.
     */
    protected function requiresTenant(): bool
    {
        return false;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === Role::Superadmin;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    /**
     * Savdoni qaytarish: egasi yoki u ruxsat bergan sotuvchi.
     */
    public function canRefund(): bool
    {
        return $this->isOwner() || ($this->isSeller() && $this->can_refund);
    }

    public function isSeller(): bool
    {
        return $this->role === Role::Seller;
    }

    /**
     * Sotuvchi biriktirilgan filiallar. Egasi uchun bu ro'yxat ishlatilmaydi: u hamma filialni ko'radi.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($branch->tenant_id !== $this->tenant_id) {
            return false;
        }

        return $this->isOwner() || $this->branches()->whereKey($branch->id)->exists();
    }
}
