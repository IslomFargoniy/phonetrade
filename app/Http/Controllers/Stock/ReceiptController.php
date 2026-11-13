<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\ReceiveStock;
use App\Enums\Currency;
use App\Enums\PurchaseType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Http\Requests\Stock\StoreReceiptRequest;
use App\Models\Attribute;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Services\Cash\CashService;
use App\Services\Rates\CurrencyRateService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function create(Request $request, CashService $cash, CurrencyRateService $rates): Response
    {
        $user = $this->user($request);
        $branch = $this->branch($request);

        return Inertia::render('receipts/create', [
            // Sotuvchi faqat mijozdan sotib oladi.
            'types' => collect(PurchaseType::cases())
                ->filter(fn (PurchaseType $type) => $user->isOwner() || $type === PurchaseType::TradeIn)
                ->map(fn (PurchaseType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
            'products' => Product::query()->with('category.attributes.options')->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image_url' => $product->imageUrl(),
                    'tracking' => $product->category->tracking->value,
                    'default_warranty_months' => $product->default_warranty_months,
                    'default_max_discount_pct' => StockPresenter::trimDecimal($product->default_max_discount_pct),
                    'attributes' => $product->category->attributes->sortBy('sort')->values()
                        ->map(fn (Attribute $attribute) => [
                            'name' => $attribute->name,
                            'type' => $attribute->type->value,
                            'options' => $attribute->options->sortBy('sort')->pluck('value')->values(),
                        ]),
                ]),
            'suppliers' => $user->isOwner() ? Supplier::query()->orderBy('name')->get(['id', 'name']) : [],
            'cashOptions' => $this->cashOptions($branch, $cash),
            'rate' => $rates->current()?->usd_uzs,
        ]);
    }

    public function store(StoreReceiptRequest $request, ReceiveStock $receiveStock): RedirectResponse
    {
        $data = $request->payload();
        $branch = $this->branch($request);

        $purchase = DB::transaction(function () use ($data, $branch, $request, $receiveStock): Purchase {
            if ($data['type'] === PurchaseType::TradeIn->value) {
                // Mijoz telefon raqami bo'yicha topiladi, bo'lmasa shu yerda yaratiladi.
                $phone = $request->string('customer_phone')->toString();
                $name = $request->string('customer_name')->trim()->toString();

                $data['customer_id'] = Customer::query()->firstOrCreate(
                    ['phone' => $phone],
                    ['full_name' => $name !== '' ? $name : Phone::format($phone)],
                )->id;
            }

            return $receiveStock->handle($branch, $this->user($request), $data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kirim saqlandi.']);

        return to_route('receipts.show', $purchase);
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        $user = $this->user($request);
        abort_unless($user->isOwner() || $purchase->user_id === $user->id, 403);

        $purchase->load(['items.product', 'items.units.product', 'items.units.photos', 'items.units.branch', 'supplier', 'customer', 'branch']);

        return Inertia::render('receipts/show', [
            'purchase' => [
                'id' => $purchase->id,
                'type_label' => $purchase->type->label(),
                'date' => $purchase->date->format('d.m.Y'),
                'branch' => $purchase->branch->name,
                'counterparty' => $purchase->supplier->name ?? $purchase->customer->full_name ?? null,
                'total_label' => StockPresenter::money($purchase->total, $purchase->currency),
                'rate' => $purchase->rate,
                'items' => $purchase->items->map(fn (PurchaseItem $item) => [
                    'id' => $item->id,
                    'product' => $item->product->name,
                    'image_url' => $item->product->imageUrl(),
                    'qty' => $item->qty,
                    'attributes' => StockPresenter::attributes($item->attributes),
                    'unit_cost_label' => StockPresenter::money($item->unit_cost, $purchase->currency),
                    'sale_price_label' => StockPresenter::money($item->sale_price, $item->sale_currency),
                    'units' => $item->units->map(fn (StockUnit $unit) => StockPresenter::unit($unit, $user->isOwner())),
                ]),
            ],
        ]);
    }

    /**
     * Mijozga to'lash mumkin bo'lgan kassalar: har kassa × valyuta, joriy qoldig'i bilan.
     *
     * @return list<array{value: string, account_id: int, currency: string, label: string}>
     */
    private function cashOptions(Branch $branch, CashService $cash): array
    {
        $options = [];

        foreach (CashAccount::query()->where('branch_id', $branch->id)->orderBy('id')->get() as $account) {
            foreach (Currency::cases() as $currency) {
                $options[] = [
                    'value' => "{$account->id}:{$currency->value}",
                    'account_id' => $account->id,
                    'currency' => $currency->value,
                    'label' => sprintf(
                        '%s · %s — balans %s',
                        $account->type->label(),
                        $currency->label(),
                        $cash->balance($account, $currency)->format(),
                    ),
                ];
            }
        }

        return $options;
    }
}
