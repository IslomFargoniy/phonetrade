<?php

namespace App\Http\Controllers\Store;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StaffRequest;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Subscription;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Do'kon xodimlari. Egasi faqat sotuvchilarni boshqaradi; o'z akkauntini Profil sahifasida o'zgartiradi.
 */
class StaffController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/staff', [
            'staff' => User::query()
                ->with('branches:id,name')
                ->orderByRaw("case when role = 'owner' then 0 else 1 end")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => Phone::format($user->phone),
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'is_active' => $user->is_active,
                    'can_refund' => $user->can_refund,
                    'branch_ids' => $user->branches->modelKeys(),
                    'branch_names' => $user->branches->pluck('name')->values(),
                ]),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StaffRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();
        $subscription->ensureCanAddUser(Tenant::query()->findOrFail($request->user()->tenant_id));

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => Role::Seller,
                'can_refund' => (bool) ($data['can_refund'] ?? false),
            ]);

            $user->branches()->sync($data['branch_ids']);

            return $user;
        });

        Audit::log('staff.created', $user, null, ['name' => $user->name, 'branch_ids' => $data['branch_ids']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Xodim qo'shildi.")]);

        return to_route('staff.index');
    }

    public function update(StaffRequest $request, User $staff, Subscription $subscription): RedirectResponse
    {
        abort_unless($staff->isSeller(), 403);

        $data = $request->validated();

        // O'chirilgan xodimni qayta yoqish ham tarif limitiga kiradi.
        if (($data['is_active'] ?? $staff->is_active) && ! $staff->is_active) {
            $subscription->ensureCanAddUser(Tenant::query()->findOrFail($request->user()->tenant_id));
        }
        $old = $staff->only(['name', 'phone', 'is_active']) + ['branch_ids' => $staff->branches()->pluck('branches.id')->all()];

        DB::transaction(function () use ($staff, $data): void {
            $staff->fill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'is_active' => $data['is_active'] ?? $staff->is_active,
                'can_refund' => $data['can_refund'] ?? $staff->can_refund,
            ]);

            if (! empty($data['password'])) {
                $staff->password = $data['password'];
            }

            $staff->save();
            $staff->branches()->sync($data['branch_ids']);
        });

        Audit::log('staff.updated', $staff, $old, $staff->only(['name', 'phone', 'is_active']) + ['branch_ids' => $data['branch_ids']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Xodim saqlandi.')]);

        return to_route('staff.index');
    }
}
