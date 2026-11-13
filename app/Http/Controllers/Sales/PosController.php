<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Http\Requests\Sales\CartRequest;
use App\Models\InstallmentRate;
use App\Services\Rates\CurrencyRateService;
use App\Services\Sales\CartPreview;
use App\Services\Stock\StockLookup;
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

    public function lookup(Request $request, StockLookup $lookup): JsonResponse
    {
        return response()->json($lookup->find($this->branch($request), (string) $request->query('code', '')));
    }

    public function preview(CartRequest $request, CartPreview $preview): JsonResponse
    {
        return response()->json($preview->for($this->branch($request), $request->items(), $request->terms()));
    }
}
