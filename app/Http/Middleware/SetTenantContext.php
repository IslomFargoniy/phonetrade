<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentBranch;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kirgan foydalanuvchining do'konini joriy kontekst qilib o'rnatadi.
 * Bloklangan do'kon yoki o'chirilgan xodim tizimdan chiqariladi.
 */
class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CurrentBranch $currentBranch,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->forget();
        $this->currentBranch->forget();

        $user = $request->user();

        if ($user instanceof User) {
            if ($reason = $this->denialReason($user)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', $reason);
            }

            if ($user->tenant_id !== null) {
                $this->context->set($user->tenant_id);
            }
        }

        try {
            return $next($request);
        } finally {
            // Bitta jarayonda ketma-ket so'rovlar (testlar, Octane) bir-biriga ta'sir qilmasligi uchun.
            $this->context->forget();
            $this->currentBranch->forget();
        }
    }

    private function denialReason(User $user): ?string
    {
        if (! $user->is_active) {
            return "Akkauntingiz o'chirilgan. Do'kon egasiga murojaat qiling.";
        }

        if ($user->tenant_id === null) {
            return $user->isSuperadmin() ? null : "Akkaunt hech bir do'konga biriktirilmagan.";
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if ($tenant === null || $tenant->isBlocked()) {
            return "Do'kon bloklangan. Qo'llab-quvvatlash xizmatiga murojaat qiling.";
        }

        return null;
    }
}
