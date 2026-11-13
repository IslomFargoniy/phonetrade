<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Cash\RecordExpense;
use App\Actions\Cash\SweepCash;
use App\Enums\CashDirection;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use App\Services\Cash\CashService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashController extends Controller
{
    private const KINDS = [
        'sale' => 'Savdo',
        'installment_initial' => "Nasiya · savdo kuni to'lovi",
        'installment_payment' => "Nasiya to'lovi",
        'trade_in' => 'Mijozdan sotib olish',
        'change' => 'Qaytim',
        'sale_return' => 'Qaytarish',
        'expense' => 'Xarajat',
        'sweep_out' => "Kassa bo'shatish",
        'sweep_in' => 'Filialdan tushum',
    ];

    public function index(Request $request, CashService $cash): Response
    {
        $user = $this->user($request);
        $branch = $this->branch($request);
        // Admin kassa faqat egasiga ko'rinadi.
        $scope = $user->isOwner() && $request->query('scope') === 'admin' ? 'admin' : 'branch';

        $accounts = CashAccount::query()
            ->when($scope === 'admin', fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branch->id))
            ->orderBy('id')
            ->get();

        $movements = CashMovement::query()
            ->with('account')
            ->whereIn('cash_account_id', $accounts->modelKeys())
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('cash/index', [
            'scope' => $scope,
            'canManage' => $user->isOwner(),
            'branchName' => $branch->name,
            'accounts' => $accounts->map(fn (CashAccount $account) => [
                'id' => $account->id,
                'label' => $account->type->label(),
                'uzs_label' => $cash->balance($account, Currency::UZS)->format(),
                'usd_label' => $cash->balance($account, Currency::USD)->format(),
            ]),
            'expenseAccounts' => $scope === 'branch' ? $this->expenseAccounts($branch, $cash) : [],
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'movements' => $movements->map(fn (CashMovement $movement) => [
                'id' => $movement->id,
                'time' => $movement->created_at?->format('d.m H:i'),
                'kind' => self::KINDS[$movement->kind] ?? $movement->kind,
                'note' => $movement->note,
                'account' => $movement->account->type->label().' · '.$movement->currency->label(),
                'in' => $movement->direction === CashDirection::In ? Money::of($movement->amount, $movement->currency)->format() : null,
                'out' => $movement->direction === CashDirection::Out ? Money::of($movement->amount, $movement->currency)->format() : null,
            ]),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(ExpenseCategory::class)->where('tenant_id', $this->user($request)->tenant_id)],
        ], attributes: ['name' => 'Kategoriya']);

        ExpenseCategory::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategoriya qo'shildi."]);

        return back();
    }

    public function storeExpense(Request $request, RecordExpense $recordExpense): RedirectResponse
    {
        $tenantId = $this->user($request)->tenant_id;

        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', Rule::exists(ExpenseCategory::class, 'id')->where('tenant_id', $tenantId)],
            // "kassa:valyuta" ko'rinishida: bitta tanlov ham kassani, ham valyutani belgilaydi
            'account' => ['required', 'string', 'regex:/^\d+:(UZS|USD)$/'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['expense_category_id' => 'Kategoriya', 'account' => 'Kassa', 'amount' => 'Summa', 'note' => 'Izoh']);

        [$accountId, $currency] = explode(':', (string) $data['account']);

        $recordExpense->handle(
            $this->branch($request),
            $this->user($request),
            (int) $data['expense_category_id'],
            (int) $accountId,
            Money::of((string) $data['amount'], Currency::from($currency)),
            isset($data['note']) ? (string) $data['note'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Xarajat saqlandi.']);

        return back();
    }

    public function sweep(Request $request, SweepCash $sweepCash): RedirectResponse
    {
        $sweepCash->handle($this->branch($request), $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kassa bo'shatildi: pul admin kassaga o'tdi."]);

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function expenseAccounts(Branch $branch, CashService $cash): array
    {
        $options = [];

        foreach (CashAccount::query()->where('branch_id', $branch->id)->orderBy('id')->get() as $account) {
            foreach (Currency::cases() as $currency) {
                $options[] = [
                    'value' => "{$account->id}:{$currency->value}",
                    'label' => sprintf('%s · %s — balans %s', $account->type->label(), $currency->label(), $cash->balance($account, $currency)->format()),
                ];
            }
        }

        return $options;
    }
}
