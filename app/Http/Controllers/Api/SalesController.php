<?php

namespace App\Http\Controllers\Api;

use App\Actions\Customers\ResolveSaleCustomer;
use App\Actions\Sales\CloseSale;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SalePresenter;
use App\Http\Presenters\StockPresenter;
use App\Http\Requests\Sales\CartRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\StockUnit;
use App\Services\Sales\CartPreview;
use App\Services\Stock\StockLookup;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobil ilova: tovar qidirish, savat hisobi va savdoni yopish.
 * Hisob-kitob web kassadagi bilan bitta kod orqali bajariladi.
 */
class SalesController extends Controller
{
    public function lookup(Request $request, StockLookup $lookup): JsonResponse
    {
        return response()->json($lookup->find($this->branch($request), (string) $request->query('code', '')));
    }

    public function stock(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $branch = $this->branch($request);
        $search = trim((string) $request->query('q', ''));

        $units = StockUnit::query()
            ->with(['product:id,name', 'branch:id,name'])
            ->where('branch_id', $branch->id)
            ->where('status', StockStatus::InStock)
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('imei1', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'units' => $units->map(fn (StockUnit $unit) => StockPresenter::unit($unit, $user->isOwner()))->values(),
        ]);
    }

    public function customer(Request $request): JsonResponse
    {
        $phone = Phone::normalize((string) $request->query('phone', ''));
        $customer = $phone ? Customer::query()->withCount('files')->where('phone', $phone)->first() : null;

        return response()->json(['customer' => $customer ? [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'phone' => $customer->phone,
            'phone2' => $customer->phone2,
            'passport_series' => $customer->passport_series,
            'passport_number' => $customer->passport_number,
            'pinfl' => $customer->pinfl,
            'address' => $customer->address,
            'has_passport_photo' => (int) $customer->getAttribute('files_count') > 0,
        ] : null]);
    }

    public function preview(CartRequest $request, CartPreview $preview): JsonResponse
    {
        return response()->json($preview->for($this->branch($request), $request->items(), $request->terms()));
    }

    public function store(CartRequest $request, CloseSale $closeSale, ResolveSaleCustomer $resolveCustomer): JsonResponse
    {
        $branch = $this->branch($request);
        $user = $this->user($request);

        $sale = DB::transaction(fn (): Sale => $closeSale->handle(
            $branch,
            $user,
            $request->items(),
            $request->payments(),
            $resolveCustomer->handle($request)?->id,
            $request->terms(),
        ));

        return response()->json($this->present($sale, $request), 201);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->isOwner() || $user->canAccessBranch($sale->branch), 403);

        return response()->json($this->present($sale, $request));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Sale $sale, Request $request): array
    {
        $sale->load(['items.unit', 'items.variant', 'payments', 'returns', 'branch', 'user:id,name', 'customer', 'plan']);

        return SalePresenter::detail($sale, $this->user($request));
    }
}
