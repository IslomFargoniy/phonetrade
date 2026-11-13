<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Subscription;
use App\Services\Rates\CurrencyRateService;
use App\Tenancy\CurrentBranch;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? $this->userProps($user) : null,
            ],
            'store' => $user instanceof User && $user->tenant_id !== null
                ? fn () => $this->storeProps($user)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userProps(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
        ];
    }

    /**
     * Do'kon ichidagi har sahifaga kerak bo'ladigan ma'lumot: do'kon nomi, filiallar, bugungi kurs.
     *
     * @return array<string, mixed>
     */
    private function storeProps(User $user): array
    {
        $branches = app(CurrentBranch::class);
        $current = $branches->resolve($user);
        $rate = app(CurrencyRateService::class)->current();

        $tenant = Tenant::query()->with('plan')->findOrFail($user->tenant_id);
        $subscription = app(Subscription::class);

        return [
            'name' => $tenant->name,
            'deletion_requested_at' => $tenant->deletion_requested_at?->format('d.m.Y'),
            'subscription' => [
                'state' => $subscription->state($tenant),
                'plan' => $tenant->plan?->name,
                'paid_until' => $tenant->paid_until?->format('d.m.Y'),
                'days_left' => $subscription->daysLeft($tenant),
                'read_only_from' => $subscription->readOnlyFrom($tenant)?->format('d.m.Y'),
            ],
            'branch' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'branches' => $branches->available($user)
                ->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name])
                ->values(),
            'rate' => $rate ? [
                'usd_uzs' => $rate->usd_uzs,
                'date' => $rate->date->toDateString(),
                'is_today' => $rate->date->isToday(),
            ] : null,
        ];
    }
}
