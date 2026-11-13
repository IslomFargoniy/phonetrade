<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tenants\CreateTenant;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Phone;
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
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->withCount(['branches', 'users'])
            ->orderByDesc('id')
            ->get();

        $owners = User::query()
            ->where('role', Role::Owner)
            ->whereIn('tenant_id', $tenants->modelKeys())
            ->get()
            ->keyBy('tenant_id');

        return Inertia::render('admin/tenants', [
            'tenants' => $tenants->map(fn (Tenant $tenant) => [
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
