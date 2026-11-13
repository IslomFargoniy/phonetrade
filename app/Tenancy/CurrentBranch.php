<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * Foydalanuvchi hozir qaysi filialda ishlayotgani. Tanlov sessiyada saqlanadi.
 */
class CurrentBranch
{
    private const SESSION_KEY = 'branch_id';

    private ?Branch $branch = null;

    private bool $resolved = false;

    public function __construct(private readonly Session $session) {}

    /**
     * Foydalanuvchi kira oladigan faol filiallar: egasi uchun hammasi, sotuvchi uchun biriktirilganlari.
     *
     * @return Collection<int, Branch>
     */
    public function available(User $user): Collection
    {
        $query = $user->isOwner() ? Branch::query() : $user->branches();

        return $query->where('is_active', true)->orderBy('name')->get();
    }

    public function resolve(User $user): ?Branch
    {
        if ($this->resolved) {
            return $this->branch;
        }

        $available = $this->available($user);

        $this->branch = $available->firstWhere('id', $this->session->get(self::SESSION_KEY))
            ?? $available->first();
        $this->resolved = true;

        $this->session->put(self::SESSION_KEY, $this->branch?->id);

        return $this->branch;
    }

    /**
     * Mobil ilova uchun: sessiya yo'q, filial har so'rovda sarlavhada keladi.
     * Ko'rsatilmagan yoki ruxsat etilmagan bo'lsa, birinchi mavjud filial olinadi.
     */
    public function selectForRequest(User $user, ?int $branchId): ?Branch
    {
        $available = $this->available($user);

        $this->branch = $available->firstWhere('id', $branchId) ?? $available->first();
        $this->resolved = true;

        return $this->branch;
    }

    public function switchTo(User $user, Branch $branch): void
    {
        abort_unless($branch->is_active && $user->canAccessBranch($branch), 403);

        $this->session->put(self::SESSION_KEY, $branch->id);
        $this->branch = $branch;
        $this->resolved = true;
    }

    public function forget(): void
    {
        $this->branch = null;
        $this->resolved = false;
    }
}
