<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin: obuna tariflari.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $usage = Tenant::query()->whereNotNull('plan_id')->selectRaw('plan_id, count(*) as total')->groupBy('plan_id')->pluck('total', 'plan_id');

        return Inertia::render('admin/plans', [
            'plans' => Plan::query()->orderBy('sort')->orderBy('id')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => rtrim(rtrim($plan->price, '0'), '.'),
                'max_branches' => $plan->max_branches,
                'max_users' => $plan->max_users,
                'is_active' => $plan->is_active,
                'tenants' => (int) ($usage[$plan->id] ?? 0),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::query()->create($this->validated($request) + ['sort' => (int) Plan::query()->max('sort') + 1]);

        Audit::log('plan.created', $plan, null, $plan->only(['name', 'price', 'max_branches', 'max_users']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __("Tarif qo'shildi.")]);

        return to_route('admin.plans.index');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $old = $plan->only(['name', 'price', 'max_branches', 'max_users', 'is_active']);
        $plan->update($this->validated($request) + ['is_active' => $request->boolean('is_active', $plan->is_active)]);

        Audit::log('plan.updated', $plan, $old, $plan->only(['name', 'price', 'max_branches', 'max_users', 'is_active']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarif saqlandi.')]);

        return to_route('admin.plans.index');
    }

    /**
     * Bo'sh limit — cheklanmagan.
     *
     * @return array{name: string, price: string, max_branches: int|null, max_users: int|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], attributes: ['name' => 'Nomi', 'price' => 'Narx', 'max_branches' => 'Filiallar', 'max_users' => 'Xodimlar']);

        return [
            'name' => (string) $data['name'],
            'price' => (string) $data['price'],
            'max_branches' => isset($data['max_branches']) ? (int) $data['max_branches'] : null,
            'max_users' => isset($data['max_users']) ? (int) $data['max_users'] : null,
        ];
    }
}
