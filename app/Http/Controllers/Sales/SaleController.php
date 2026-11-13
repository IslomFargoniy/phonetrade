<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CloseSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\CashAccountType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SalePresenter;
use App\Http\Requests\Sales\CartRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $branch = $this->branch($request);
        $search = trim((string) $request->query('q', ''));
        $phone = Phone::normalize($search);

        $sales = Sale::query()
            ->with(['items', 'user:id,name', 'customer:id,full_name,phone'])
            // Sotuvchi faqat o'z filiali savdolarini ko'radi.
            ->when(! $user->isOwner(), fn ($query) => $query->where('branch_id', $branch->id))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->when(ctype_digit($search), fn ($number) => $number->orWhere('number', (int) $search))
                ->orWhereHas('items', fn ($items) => $items
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('unit', fn ($unit) => $unit
                        ->where('imei1', $search)->orWhere('imei2', $search)->orWhere('barcode', $search)))
                ->when($phone !== null, fn ($byPhone) => $byPhone->orWhereHas('customer', fn ($customer) => $customer->where('phone', $phone)))))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('sales/index', [
            'q' => $search,
            'sales' => $sales->map(fn (Sale $sale) => SalePresenter::summary($sale)),
        ]);
    }

    public function store(CartRequest $request, CloseSale $closeSale): RedirectResponse
    {
        $branch = $this->branch($request);

        $sale = DB::transaction(function () use ($request, $closeSale, $branch): Sale {
            $phone = $request->string('customer_phone')->toString();
            $name = $request->string('customer_name')->trim()->toString();

            // To'liq to'lovda mijoz ixtiyoriy; telefon kiritilsa topiladi yoki yaratiladi.
            $customerId = $phone !== ''
                ? Customer::query()->firstOrCreate(['phone' => $phone], ['full_name' => $name !== '' ? $name : Phone::format($phone)])->id
                : null;

            return $closeSale->handle($branch, $this->user($request), $request->items(), $request->payments(), $customerId);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Savdo yopildi.']);

        return to_route('sales.show', ['sale' => $sale, 'print' => 1]);
    }

    public function show(Request $request, Sale $sale): Response
    {
        $user = $this->user($request);
        $this->authorizeSale($user, $sale);

        $sale->load(['items.unit', 'items.variant', 'payments', 'returns', 'branch', 'user:id,name', 'customer']);
        $tenant = Tenant::query()->findOrFail($user->tenant_id);

        return Inertia::render('sales/show', [
            'sale' => SalePresenter::detail($sale, $user),
            'canRefund' => $user->canRefund(),
            'autoPrint' => $request->boolean('print'),
            'refundMethods' => collect(CashAccountType::cases())->map(fn (CashAccountType $type) => ['value' => $type->value, 'label' => $type->label()]),
            'receipt' => [
                'name' => $tenant->setting('receipt.name', $tenant->name),
                'phone' => $tenant->setting('receipt.phone'),
                'footer' => $tenant->setting('receipt.footer'),
            ],
        ]);
    }

    public function refund(Request $request, Sale $sale, ReturnSale $returnSale): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeSale($user, $sale);
        abort_unless($user->canRefund(), 403);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', Rule::exists(SaleItem::class, 'id')->where('sale_id', $sale->id)],
            'items.*.qty' => ['nullable', 'integer', 'min:0'],
            'refund_from' => ['required', Rule::enum(CashAccountType::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ], attributes: ['refund_from' => 'Kassa', 'reason' => 'Sabab']);

        $items = [];

        foreach (array_values((array) $data['items']) as $item) {
            if ((int) ($item['qty'] ?? 0) > 0) {
                $items[] = ['sale_item_id' => (int) $item['sale_item_id'], 'qty' => (int) $item['qty']];
            }
        }

        $returnSale->handle(
            $sale,
            $user,
            $items,
            $request->enum('refund_from', CashAccountType::class) ?? CashAccountType::Cash,
            isset($data['reason']) ? (string) $data['reason'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Savdo qaytarildi.']);

        return to_route('sales.show', $sale);
    }

    private function authorizeSale(User $user, Sale $sale): void
    {
        abort_unless($user->isOwner() || $user->canAccessBranch($sale->branch), 403);
    }
}
