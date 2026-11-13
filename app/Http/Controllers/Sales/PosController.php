<?php

namespace App\Http\Controllers\Sales;

use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\InstallmentPresenter;
use App\Http\Presenters\SalePresenter;
use App\Http\Presenters\StockPresenter;
use App\Http\Requests\Sales\CartRequest;
use App\Models\Branch;
use App\Models\InstallmentRate;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Services\Installments\InstallmentPlanner;
use App\Services\Rates\CurrencyRateService;
use App\Services\Sales\CartCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kassa oynasi: tovarni topish va savatni hisoblash.
 */
class PosController extends Controller
{
    public function index(Request $request, CurrencyRateService $rates): Response
    {
        $branch = $this->branch($request);

        return Inertia::render('pos/index', [
            'rate' => $rates->current()?->usd_uzs,
            'installmentRates' => InstallmentRate::query()->where('branch_id', $branch->id)->orderBy('months')->get()
                ->map(fn (InstallmentRate $installmentRate) => [
                    'months' => $installmentRate->months,
                    'markup_pct' => StockPresenter::trimDecimal($installmentRate->markup_pct),
                ]),
        ]);
    }

    /**
     * IMEI yoki barcode aniq mos kelsa bitta tovar qaytadi (skaner uchun);
     * aks holda model nomi bo'yicha qidiriladi. Tannarx bu yerda hech kimga
     * berilmaydi: kassa ekrani mijozga ham ko'rinadi.
     */
    public function lookup(Request $request): JsonResponse
    {
        $branch = $this->branch($request);
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return response()->json(['exact' => false, 'units' => [], 'variants' => []]);
        }

        $units = StockUnit::query()
            ->with(['product:id,name', 'branch:id,name'])
            ->where('branch_id', $branch->id)
            ->where('status', StockStatus::InStock)
            ->where(fn ($query) => $query->where('imei1', $code)->orWhere('imei2', $code)->orWhere('barcode', $code))
            ->limit(1)
            ->get();

        $variants = $units->isEmpty()
            ? ProductVariant::query()->with('product:id,name')->where('barcode', $code)->limit(1)->get()
            : collect();

        $exact = $units->isNotEmpty() || $variants->isNotEmpty();

        if (! $exact && mb_strlen($code) >= 2) {
            $units = StockUnit::query()
                ->with(['product:id,name', 'branch:id,name'])
                ->where('branch_id', $branch->id)
                ->where('status', StockStatus::InStock)
                ->where(fn ($query) => $query
                    ->where('imei1', 'like', "%{$code}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$code}%")))
                ->orderBy('id')
                ->limit(8)
                ->get();

            $variants = ProductVariant::query()
                ->with('product:id,name')
                ->whereHas('product', fn ($product) => $product->where('name', 'like', "%{$code}%"))
                ->limit(8)
                ->get();
        }

        return response()->json([
            'exact' => $exact,
            'units' => $units->map(fn (StockUnit $unit) => StockPresenter::unit($unit, withCost: false))->values(),
            'variants' => $this->presentVariants($variants->all(), $branch),
        ]);
    }

    /**
     * Savat jami: ekranda ko'rsatiladigan raqamlar savdoni yopishdagi hisob bilan bir xil.
     */
    public function preview(CartRequest $request, CartCalculator $calculator, CurrencyRateService $rates, InstallmentPlanner $planner): JsonResponse
    {
        $branch = $this->branch($request);
        $rate = $rates->current()?->usd_uzs;
        $items = [];

        foreach ($request->items() as $item) {
            $items[] = [
                'unit' => $item['stock_unit_id'] !== null
                    ? StockUnit::query()->with('product')->where('branch_id', $branch->id)->find($item['stock_unit_id'])
                    : null,
                'variant' => $item['variant_id'] !== null
                    ? ProductVariant::query()->with('product')->find($item['variant_id'])
                    : null,
                'qty' => $item['qty'],
                'discount' => $item['discount'],
            ];
        }

        $totals = $calculator->calculate($items, $rate);
        $terms = $request->terms();

        return response()->json([
            ...SalePresenter::totals($totals, $rate),
            'installment' => $terms !== null
                ? InstallmentPresenter::quote($planner->quote($branch, $totals, $terms, $rate))
                : null,
        ]);
    }

    /**
     * @param  array<int, ProductVariant>  $variants
     * @return list<array<string, mixed>>
     */
    private function presentVariants(array $variants, Branch $branch): array
    {
        if ($variants === []) {
            return [];
        }

        $quantities = StockBatch::query()
            ->where('branch_id', $branch->id)
            ->whereIn('variant_id', array_map(fn (ProductVariant $variant) => $variant->id, $variants))
            ->selectRaw('variant_id, sum(qty_left) as qty')
            ->groupBy('variant_id')
            ->pluck('qty', 'variant_id');

        $presented = [];

        foreach ($variants as $variant) {
            $qty = (int) ($quantities[$variant->id] ?? 0);

            if ($qty > 0) {
                $presented[] = StockPresenter::variant($variant, $qty);
            }
        }

        return $presented;
    }
}
