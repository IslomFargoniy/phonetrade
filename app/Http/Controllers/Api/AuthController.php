<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Models\Branch;
use App\Models\InstallmentRate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rates\CurrencyRateService;
use App\Support\Phone;
use App\Tenancy\CurrentBranch;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, TenantContext $context): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $phone = Phone::normalize((string) $data['phone']);
        $user = $phone !== null ? User::query()->where('phone', $phone)->first() : null;

        if ($user === null || ! Hash::check((string) $data['password'], $user->password)) {
            return response()->json(['code' => 'INVALID_CREDENTIALS', 'message' => __('auth.failed')], 401);
        }

        // Mobil ilova do'kon xodimlari uchun: superadmin faqat web panelda ishlaydi.
        if ($user->tenant_id === null || ! $user->is_active) {
            return response()->json(['code' => 'ACCOUNT_DISABLED', 'message' => 'Bu akkaunt bilan mobil ilovaga kirib bo\'lmaydi.'], 403);
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if ($tenant === null || $tenant->isBlocked()) {
            return response()->json(['code' => 'ACCOUNT_DISABLED', 'message' => "Do'kon bloklangan."], 403);
        }

        $context->set($tenant);

        return response()->json([
            'token' => $user->createToken((string) $data['device_name'])->plainTextToken,
            ...$this->profile($user, $request),
        ]);
    }

    /**
     * Demo rejimi: namuna do'konning demo xodimi nomidan parolsiz kirish.
     * Faqat `APP_DEMO_PHONE` sozlangan bo'lsa ishlaydi; demo xodim faqat namuna do'kon ma'lumotini ko'radi.
     */
    public function demo(Request $request, TenantContext $context): JsonResponse
    {
        $phone = Phone::normalize((string) config('app.demo_phone'));
        $user = $phone !== null ? User::query()->where('phone', $phone)->where('is_active', true)->first() : null;
        $tenant = $user?->tenant_id !== null ? Tenant::query()->find($user->tenant_id) : null;

        if ($user === null || $tenant === null || $tenant->isBlocked()) {
            return response()->json(['code' => 'DEMO_UNAVAILABLE', 'message' => 'Demo rejimi hozir mavjud emas.'], 404);
        }

        $context->set($tenant);

        // Eski demo tokenlar to'planib qolmasligi uchun bir kundan oshganlari o'chiriladi.
        $user->tokens()->where('name', 'demo')->where('created_at', '<', now()->subDay())->delete();

        return response()->json([
            'token' => $user->createToken('demo')->plainTextToken,
            ...$this->profile($user, $request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->user($request)->currentAccessToken()->delete();

        return response()->json(['message' => 'Chiqildi.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profile($this->user($request), $request));
    }

    /**
     * Ilova ochilganda kerak bo'ladigan hamma narsa: foydalanuvchi, filiallar, kurs, nasiya muddatlari.
     *
     * @return array<string, mixed>
     */
    private function profile(User $user, Request $request): array
    {
        $branches = app(CurrentBranch::class);
        $current = $branches->selectForRequest($user, (int) $request->header('X-Branch-Id') ?: null);
        $rate = app(CurrencyRateService::class)->current();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'can_refund' => $user->canRefund(),
            ],
            'store' => [
                'name' => Tenant::query()->whereKey($user->tenant_id)->value('name'),
                'branch' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
                'branches' => $branches->available($user)->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name])->values(),
                'rate' => $rate?->usd_uzs,
                'installment_rates' => $current
                    ? InstallmentRate::query()->where('branch_id', $current->id)->orderBy('months')->get()
                        ->map(fn (InstallmentRate $row) => ['months' => $row->months, 'markup_pct' => StockPresenter::trimDecimal($row->markup_pct)])
                    : [],
            ],
        ];
    }
}
