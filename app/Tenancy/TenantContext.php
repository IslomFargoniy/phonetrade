<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Joriy so'rov qaysi do'kon nomidan ishlayotganini saqlaydi.
 *
 * O'rnatilgan bo'lsa, BelongsToTenant ishlatadigan barcha modellar faqat shu
 * do'kon ma'lumotini ko'radi. O'rnatilmagan holat — superadmin va konsol
 * buyruqlari: ular barcha do'konlarni ko'radi.
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(Tenant|int $tenant): void
    {
        $this->tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Callback'ni berilgan do'kon nomidan bajaradi, keyin avvalgi holatga qaytaradi.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runFor(Tenant|int $tenant, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
