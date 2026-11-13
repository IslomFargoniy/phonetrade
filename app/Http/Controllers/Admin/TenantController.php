<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tenants\CreateTenant;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Sale;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Subscription;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin paneli: do'konlar ro'yxati, yangi do'kon ochish, bloklash.
 */
class TenantController extends Controller
{
    public function index(Subscription $subscription): Response
    {
        $tenants = Tenant::query()
            ->with('plan')
            ->withCount(['branches', 'users'])
            ->orderByDesc('id')
            ->get();

        $owners = User::query()
            ->where('role', Role::Owner)
            ->whereIn('tenant_id', $tenants->modelKeys())
            ->get()
            ->keyBy('tenant_id');

        $monthStart = CarbonImmutable::today()->startOfMonth();

        return Inertia::render('admin/tenants', [
            // Platforma bo'yicha umumiy ko'rsatkichlar (barcha do'konlar).
            'stats' => [
                'tenants' => $tenants->count(),
                'blocked' => $tenants->filter(fn (Tenant $tenant) => $tenant->isBlocked())->count(),
                'expired' => $tenants->filter(fn (Tenant $tenant) => $subscription->state($tenant) === Subscription::STATE_EXPIRED)->count(),
                'grace' => $tenants->filter(fn (Tenant $tenant) => $subscription->state($tenant) === Subscription::STATE_GRACE)->count(),
                'branches' => (int) $tenants->sum('branches_count'),
                'users' => (int) $tenants->sum('users_count'),
                'sales_month' => Sale::query()->where('created_at', '>=', $monthStart)->count(),
                'income_month' => Money::uzs((string) SubscriptionPayment::query()->where('created_at', '>=', $monthStart)->sum('amount'))->format(),
            ],
            'tenants' => $tenants->map(fn (Tenant $tenant) => [
                'plan' => $tenant->plan?->name,
                'paid_until' => $tenant->paid_until?->format('d.m.Y'),
                'state' => $subscription->state($tenant),
                'deletion_requested' => $tenant->deletion_requested_at !== null,
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
                'branches_count' => $tenant->branches_count,
                'users_count' => $tenant->users_count,
                'owner_name' => $owners->get($tenant->id)?->name,
                'owner_phone' => ($phone = $owners->get($tenant->id)?->phone) ? Phone::format($phone) : null,
                'created_at' => $tenant->created_at?->format('d.m.Y'),
            ]),
        ]);
    }

    public function store(StoreTenantRequest $request, CreateTenant $createTenant): RedirectResponse
    {
        $tenant = $createTenant->handle(
            name: $request->string('name')->toString(),
            branchName: $request->string('branch_name')->toString(),
            ownerName: $request->string('owner_name')->toString(),
            ownerPhone: $request->string('owner_phone')->toString(),
            ownerPassword: $request->string('owner_password')->toString(),
        );

        Audit::log('tenant.created', $tenant, null, ['name' => $tenant->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Do'kon ochildi.")]);

        return to_route('admin.tenants.index');
    }

    public function updateStatus(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ]);

        $old = $tenant->status;
        $tenant->update(['status' => $data['status']]);

        Audit::log('tenant.status_changed', $tenant, ['status' => $old->value], ['status' => $tenant->status->value]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $tenant->isBlocked() ? __("Do'kon bloklandi.") : __("Do'kon blokdan chiqarildi."),
        ]);

        return to_route('admin.tenants.index');
    }
}
