<?php

namespace App\Http\Controllers\Stock;

use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Support\Audit;
use App\Tenancy\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    private const LIMIT = 200;

    public function index(Request $request, CurrentBranch $currentBranch): Response
    {
        $user = $this->user($request);
        $branch = $this->branch($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['all', 'phone', 'accessory'])],
            'status' => ['nullable', Rule::enum(StockStatus::class)],
            'scope' => ['nullable', Rule::in(['branch', 'all'])],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $kind = $filters['kind'] ?? 'all';
        $status = $filters['status'] ?? StockStatus::InStock->value;
        // Barcha filiallar bo'yicha ko'rish faqat egasiga.
        $allBranches = $user->isOwner() && ($filters['scope'] ?? 'branch') === 'all';

        $units = collect();
        $variants = collect();

        if ($kind !== 'accessory') {
            $units = StockUnit::query()
                ->with(['product:id,name,image_path', 'branch:id,name', 'photos'])
                ->where('status', $status)
                ->when(! $allBranches, fn ($query) => $query->where('branch_id', $branch->id))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('imei1', 'like', "%{$search}%")
                    ->orWhere('imei2', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (StockUnit $unit) => StockPresenter::unit($unit, $user->isOwner()));
        }

        if ($kind !== 'phone' && $status === StockStatus::InStock->value) {
            $quantities = StockBatch::query()
                ->when(! $allBranches, fn ($query) => $query->where('branch_id', $branch->id))
                ->where('qty_left', '>', 0)
                ->selectRaw('variant_id, sum(qty_left) as qty')
                ->groupBy('variant_id')
                ->pluck('qty', 'variant_id');

            $variants = ProductVariant::query()
                ->with('product:id,name,image_path')
                ->whereIn('id', $quantities->keys())
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('barcode', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (ProductVariant $variant) => StockPresenter::variant($variant, (int) $quantities[$variant->id]));
        }

        return Inertia::render('stock/index', [
            'filters' => ['q' => $search, 'kind' => $kind, 'status' => $status, 'scope' => $allBranches ? 'all' : 'branch'],
            'statuses' => collect(StockStatus::cases())->map(fn (StockStatus $case) => ['value' => $case->value, 'label' => $case->label()]),
            'units' => $units->values(),
            'variants' => $variants->values(),
            'canSeeCost' => $user->isOwner(),
            'canEdit' => $user->isOwner(),
            'transferBranches' => $currentBranch->available($user)
                ->reject(fn (Branch $other) => $other->is($branch))
                ->map(fn (Branch $other) => ['id' => $other->id, 'name' => $other->name])
                ->values(),
            'allBranches' => $user->isOwner()
                ? Branch::query()->where('is_active', true)->whereKeyNot($branch->id)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * Qaytgan telefonni tekshiruvdan keyin yana sotuvga chiqarish — faqat egasi.
     */
    public function restock(StockUnit $unit): RedirectResponse
    {
        abort_unless($unit->status === StockStatus::Returned, 422);

        $unit->update(['status' => StockStatus::InStock]);
        Audit::log('stock_unit.restocked', $unit);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Telefon sotuvga qaytarildi.']);

        return back();
    }

    /**
     * Donaning sotuv narxi va chegirma chegarasini o'zgartirish — faqat egasi.
     */
    public function updateUnit(Request $request, StockUnit $unit): RedirectResponse
    {
        $data = $request->validate([
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'sale_currency' => ['required', Rule::enum(Currency::class)],
            'max_discount_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'warranty_months' => ['required', 'integer', 'min:0', 'max:120'],
        ], attributes: [
            'sale_price' => 'Sotuv narxi',
            'max_discount_pct' => 'Max chegirma',
            'warranty_months' => 'Kafolat',
        ]);

        $fields = ['sale_price', 'sale_currency', 'max_discount_pct', 'warranty_months'];
        $old = $unit->only($fields);
        $unit->update($data);

        Audit::log('stock_unit.price_changed', $unit, $old, $unit->only($fields));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Narx saqlandi.']);

        return back();
    }
}
