<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Do'konga tegishli har bir model shu trait'ni ishlatadi.
 *
 * - O'qishda: joriy do'kon bo'yicha avtomatik filtrlanadi.
 * - Yozishda: tenant_id joriy do'kondan olinadi; do'kon aniqlanmagan bo'lsa
 *   yozuv yaratilmaydi (egasiz qator paydo bo'lmasligi uchun).
 *
 * @property int|null $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null && $model->requiresTenant()) {
                throw new LogicException(sprintf(
                    '%s yozuvini yaratish uchun do\'kon aniqlanmagan.',
                    static::class,
                ));
            }

            $model->tenant_id = $tenantId;
        });
    }

    /**
     * Do'konsiz yozuv bo'lishi mumkinmi (masalan, superadmin foydalanuvchi).
     */
    protected function requiresTenant(): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
