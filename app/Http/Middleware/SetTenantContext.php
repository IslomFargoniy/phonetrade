<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Subscription;
use App\Tenancy\CurrentBranch;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
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
                if ($request->is('api/*')) {
                    // Mobil ilova: token bekor qilinadi, ilova login ekraniga qaytadi.
                    $user->currentAccessToken()->delete();

                    return response()->json(['code' => 'ACCOUNT_DISABLED', 'message' => $reason], 403);
                }

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', $reason);
            }

            if ($user->tenant_id !== null) {
                $this->context->set($user->tenant_id);

                if ($response = $this->readOnlyResponse($request, $user)) {
                    return $response;
                }
            }

            if ($request->is('api/*')) {
                $this->currentBranch->selectForRequest($user, $request->integer('branch_id') ?: ((int) $request->header('X-Branch-Id') ?: null));
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

    /**
     * Obuna muddati (imtiyozli davr bilan) tugagan do'kon faqat o'qiy oladi:
     * ma'lumot ko'rinadi, lekin o'zgartiradigan so'rovlar rad etiladi.
     */
    private function readOnlyResponse(Request $request, User $user): ?Response
    {
        if ($request->isMethodSafe() || $request->routeIs('logout', 'api.logout', 'branches.select')) {
            return null;
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if ($tenant === null || ! app(Subscription::class)->isReadOnly($tenant)) {
            return null;
        }

        $message = $user->isOwner()
            ? "Obuna muddati tugagan. Do'kon faqat o'qish rejimida — davom ettirish uchun to'lovni amalga oshiring."
            : "Obuna muddati tugagan. Do'kon egasiga murojaat qiling.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['code' => 'SUBSCRIPTION_EXPIRED', 'message' => $message], 402);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back()->withErrors(['business' => $message]);
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
