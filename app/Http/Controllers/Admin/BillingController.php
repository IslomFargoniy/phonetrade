<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Subscription;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin: do'konning tarifi, obuna muddati va to'lovlar tarixi.
 */
class BillingController extends Controller
{
    public function show(Tenant $tenant, Subscription $subscription): Response
    {
        $tenant->load('plan');

        return Inertia::render('admin/tenant', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
                'created_at' => $tenant->created_at?->format('d.m.Y'),
                'plan_id' => $tenant->plan_id,
                'plan' => $tenant->plan?->name,
                'paid_until' => $tenant->paid_until?->toDateString(),
                'paid_until_label' => $tenant->paid_until?->format('d.m.Y'),
                'state' => $subscription->state($tenant),
                'days_left' => $subscription->daysLeft($tenant),
                'branches' => Branch::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
                'max_branches' => $tenant->plan?->max_branches,
                'sellers' => User::query()->where('tenant_id', $tenant->id)->where('role', Role::Seller)->where('is_active', true)->count(),
                'max_users' => $tenant->plan?->max_users,
            ],
            'plans' => Plan::query()->where('is_active', true)->orWhere('id', $tenant->plan_id)->orderBy('sort')->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'price_label' => Money::uzs($plan->price)->format(),
                ]),
            'payments' => SubscriptionPayment::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(100)->get()
                ->map(fn (SubscriptionPayment $payment) => [
                    'id' => $payment->id,
                    'date' => $payment->created_at?->format('d.m.Y'),
                    'plan' => $payment->plan_name,
                    'months' => $payment->months,
                    'amount_label' => Money::uzs($payment->amount)->format(),
                    'period' => $payment->period_from->format('d.m.Y').' — '.$payment->period_to->format('d.m.Y'),
                    'note' => $payment->note,
                ]),
        ]);
    }

    /**
     * To'lovsiz o'zgartirish: sinov muddati berish, tarifni almashtirish yoki xatoni tuzatish.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')],
            'paid_until' => ['nullable', 'date'],
        ], attributes: ['plan_id' => 'Tarif', 'paid_until' => 'Muddat']);

        $old = ['plan_id' => $tenant->plan_id, 'paid_until' => $tenant->paid_until?->toDateString()];

        $tenant->forceFill([
            'plan_id' => $data['plan_id'] ?? null,
            'paid_until' => $data['paid_until'] ?? null,
        ])->save();

        Audit::log('tenant.subscription_changed', $tenant, $old, [
            'plan_id' => $tenant->plan_id,
            'paid_until' => $tenant->paid_until?->toDateString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Obuna saqlandi.')]);

        return to_route('admin.tenants.show', $tenant);
    }

    public function storePayment(Request $request, Tenant $tenant, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['plan_id' => 'Tarif', 'months' => 'Oylar soni', 'amount' => 'Summa', 'note' => 'Izoh']);

        $payment = $subscription->recordPayment(
            $tenant,
            Plan::query()->whereKey((int) $data['plan_id'])->firstOrFail(),
            (int) $data['months'],
            (string) $data['amount'],
            $data['note'] ?? null,
            $request->user(),
        );

        Audit::log('tenant.subscription_paid', $tenant, null, [
            'amount' => $payment->amount,
            'months' => $payment->months,
            'period_to' => $payment->period_to->toDateString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('To\'lov yozildi. Muddat: :date gacha.', ['date' => $payment->period_to->format('d.m.Y')]),
        ]);

        return to_route('admin.tenants.show', $tenant);
    }
}
