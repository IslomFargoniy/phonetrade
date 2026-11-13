<?php

namespace App\Http\Controllers\Installments;

use App\Actions\Installments\AcceptInstallmentPayment;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\InstallmentPresenter;
use App\Models\InstallmentPlan;
use App\Models\Tenant;
use App\Services\Rates\CurrencyRateService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstallmentController extends Controller
{
    public function index(Request $request, CurrencyRateService $rates): Response
    {
        $user = $this->user($request);
        $branch = $this->branch($request);
        $filter = in_array($request->query('filter'), ['today', 'late', 'active', 'closed'], true)
            ? (string) $request->query('filter')
            : 'active';
        $search = trim((string) $request->query('q', ''));
        $today = now()->toDateString();

        $base = fn () => InstallmentPlan::query()
            // Sotuvchi faqat o'z filiali shartnomalarini ko'radi.
            ->when(! $user->isOwner(), fn ($query) => $query->where('branch_id', $branch->id));

        $unpaid = fn ($rows) => $rows->whereColumn('paid_amount', '<', 'amount');

        $scope = fn (string $name) => match ($name) {
            'today' => $base()->where('status', PlanStatus::Active)
                ->whereHas('schedule', fn ($rows) => $unpaid($rows)->whereDate('due_date', $today)),
            'late' => $base()->where('status', PlanStatus::Active)
                ->whereHas('schedule', fn ($rows) => $unpaid($rows)->whereDate('due_date', '<', $today)),
            'closed' => $base()->whereIn('status', [PlanStatus::Closed, PlanStatus::Cancelled]),
            default => $base()->where('status', PlanStatus::Active),
        };

        $plans = $scope($filter)
            ->with(['customer', 'sale.items', 'schedule' => fn ($rows) => $rows->orderBy('no'), 'payments'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('customer', fn ($customer) => $customer
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', '%'.preg_replace('/\D+/', '', $search).'%'))
                ->orWhereHas('sale.items.unit', fn ($unit) => $unit->where('imei1', $search))))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('installments/index', [
            'filter' => $filter,
            'q' => $search,
            'counts' => [
                'today' => $scope('today')->count(),
                'late' => $scope('late')->count(),
                'active' => $scope('active')->count(),
            ],
            'plans' => $plans->map(fn (InstallmentPlan $plan) => InstallmentPresenter::plan($plan))->values(),
            'rate' => $rates->current()?->usd_uzs,
            'storeName' => Tenant::query()->whereKey($user->tenant_id)->value('name'),
        ]);
    }

    public function pay(Request $request, InstallmentPlan $plan, AcceptInstallmentPayment $accept): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->isOwner() || $user->canAccessBranch($plan->branch), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'method' => ['required', Rule::enum(CashAccountType::class)],
        ], attributes: ['amount' => 'Summa', 'currency' => 'Valyuta', 'method' => "To'lov usuli"]);

        $accept->handle(
            $plan,
            $this->branch($request),
            $user,
            Money::of((string) $data['amount'], $request->enum('currency', Currency::class) ?? Currency::UZS),
            $request->enum('method', CashAccountType::class) ?? CashAccountType::Cash,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "To'lov qabul qilindi."]);

        return back();
    }
}
