<?php

namespace App\Http\Controllers\Api;

use App\Actions\Installments\AcceptInstallmentPayment;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\InstallmentPresenter;
use App\Models\InstallmentPlan;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstallmentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $branch = $this->branch($request);
        $filter = in_array($request->query('filter'), ['today', 'late', 'active'], true) ? (string) $request->query('filter') : 'active';
        $today = now()->toDateString();

        $plans = InstallmentPlan::query()
            ->with(['customer', 'sale.items', 'schedule' => fn ($rows) => $rows->orderBy('no'), 'payments'])
            ->where('status', PlanStatus::Active)
            ->when(! $user->isOwner(), fn ($query) => $query->where('branch_id', $branch->id))
            ->when($filter === 'today', fn ($query) => $query->whereHas('schedule', fn ($rows) => $rows
                ->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', $today)))
            ->when($filter === 'late', fn ($query) => $query->whereHas('schedule', fn ($rows) => $rows
                ->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', '<', $today)))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'plans' => $plans->map(fn (InstallmentPlan $plan) => InstallmentPresenter::plan($plan))->values(),
        ]);
    }

    public function show(Request $request, InstallmentPlan $plan): JsonResponse
    {
        $this->authorizePlan($request, $plan);

        return response()->json($this->present($plan));
    }

    public function pay(Request $request, InstallmentPlan $plan, AcceptInstallmentPayment $accept): JsonResponse
    {
        $this->authorizePlan($request, $plan);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'method' => ['required', Rule::enum(CashAccountType::class)],
        ]);

        $accept->handle(
            $plan,
            $this->branch($request),
            $this->user($request),
            Money::of((string) $data['amount'], $request->enum('currency', Currency::class) ?? Currency::UZS),
            $request->enum('method', CashAccountType::class) ?? CashAccountType::Cash,
        );

        return response()->json($this->present($plan->refresh()), 201);
    }

    private function authorizePlan(Request $request, InstallmentPlan $plan): void
    {
        $user = $this->user($request);

        abort_unless($user->isOwner() || $user->canAccessBranch($plan->branch), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InstallmentPlan $plan): array
    {
        $plan->load(['customer', 'sale.items', 'schedule' => fn ($rows) => $rows->orderBy('no'), 'payments']);

        return InstallmentPresenter::plan($plan);
    }
}
