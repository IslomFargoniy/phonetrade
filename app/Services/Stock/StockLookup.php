<?php

namespace App\Services\Stock;

use App\Enums\StockStatus;
use App\Http\Presenters\StockPresenter;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockUnit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kassa uchun tovar qidiruvi (web va mobil ilova bir xil ishlatadi).
 *
 * IMEI yoki barcode aniq mos kelsa bitta tovar qaytadi (skaner uchun); aks holda
 * model nomi bo'yicha qidiriladi. Tannarx bu yerda hech kimga berilmaydi: kassa
 * ekrani mijozga ham ko'rinadi.
 */
class StockLookup
{
    /**
     * @return array{exact: bool, units: list<array<string, mixed>>, variants: list<array<string, mixed>>}
     */
    public function find(Branch $branch, string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            return ['exact' => false, 'units' => [], 'variants' => []];
        }

        $units = $this->units($branch)
            ->where(fn ($query) => $query->where('imei1', $code)->orWhere('imei2', $code)->orWhere('barcode', $code))
            ->limit(1)
            ->get();

        $variants = $units->isEmpty()
            ? ProductVariant::query()->with('product:id,name')->where('barcode', $code)->limit(1)->get()
            : collect();

        $presentedVariants = $this->presentVariants($variants->all(), $branch);
        $exact = $units->isNotEmpty() || $presentedVariants !== [];

        if (! $exact && mb_strlen($code) >= 2) {
            $units = $this->units($branch)
                ->where(fn ($query) => $query
                    ->where('imei1', 'like', "%{$code}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$code}%")))
                ->orderBy('id')
                ->limit(8)
                ->get();

            $presentedVariants = $this->presentVariants(
                ProductVariant::query()
                    ->with('product:id,name')
                    ->whereHas('product', fn ($product) => $product->where('name', 'like', "%{$code}%"))
                    ->limit(8)
                    ->get()
                    ->all(),
                $branch,
            );
        }

        return [
            'exact' => $exact,
            'units' => array_values($units->map(fn (StockUnit $unit) => StockPresenter::unit($unit, withCost: false))->all()),
            'variants' => $presentedVariants,
        ];
    }

    /**
     * @return Builder<StockUnit>
     */
    private function units(Branch $branch)
    {
        return StockUnit::query()
            ->with(['product:id,name', 'branch:id,name'])
            ->where('branch_id', $branch->id)
            ->where('status', StockStatus::InStock);
    }

    /**
     * Faqat shu filialda qoldig'i bor aksesuarlar.
     *
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
