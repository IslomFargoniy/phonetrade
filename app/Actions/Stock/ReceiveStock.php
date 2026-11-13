<?php

namespace App\Actions\Stock;

use App\Enums\Currency;
use App\Enums\PurchaseType;
use App\Enums\StockStatus;
use App\Enums\Tracking;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Rates\CurrencyRateService;
use App\Services\Stock\BarcodeGenerator;
use App\Services\Stock\Imei;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Tovar kirimi: yetkazib beruvchidan, boshlang'ich qoldiq yoki mijozdan sotib olish.
 *
 * Telefonlar donalab (har biri o'z IMEI va barcode'i bilan), aksesuarlar partiya
 * bo'lib kiradi. Mijozdan sotib olishda to'langan summa tanlangan kassadan chiqadi;
 * kassada pul yetmasa butun kirim bekor bo'ladi.
 *
 * @phpstan-type Unit array{imei1: string, imei2?: string|null}
 * @phpstan-type Line array{
 *     product_id: int,
 *     attributes?: array<string, string>|null,
 *     unit_cost: string,
 *     sale_price: string,
 *     sale_currency: string,
 *     max_discount_pct?: string|null,
 *     warranty_months?: int|null,
 *     units?: list<Unit>|null,
 *     qty?: int|null,
 *     barcode?: string|null
 * }
 * @phpstan-type Payload array{
 *     type: string,
 *     currency: string,
 *     supplier_id?: int|null,
 *     customer_id?: int|null,
 *     cash_account_id?: int|null,
 *     note?: string|null,
 *     lines: list<Line>
 * }
 */
class ReceiveStock
{
    public function __construct(
        private readonly CashService $cash,
        private readonly CurrencyRateService $rates,
        private readonly BarcodeGenerator $barcodes,
    ) {}

    /**
     * @param  Payload  $data
     */
    public function handle(Branch $branch, User $user, array $data): Purchase
    {
        $type = PurchaseType::from($data['type']);
        $currency = Currency::from($data['currency']);
        $rate = $this->rates->current()?->usd_uzs;

        return DB::transaction(function () use ($branch, $user, $data, $type, $currency, $rate): Purchase {
            $this->assertRateAvailable($currency, $data['lines'], $rate);
            $this->assertImeisAreFree($data['lines']);

            $purchase = Purchase::query()->create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'type' => $type,
                'supplier_id' => $type === PurchaseType::Supplier ? ($data['supplier_id'] ?? null) : null,
                'customer_id' => $type === PurchaseType::TradeIn ? ($data['customer_id'] ?? null) : null,
                'cash_account_id' => $type === PurchaseType::TradeIn ? ($data['cash_account_id'] ?? null) : null,
                'date' => now()->toDateString(),
                'currency' => $currency,
                'rate' => $rate,
                'total' => '0',
                'note' => $data['note'] ?? null,
            ]);

            $total = Money::zero($currency);

            foreach ($data['lines'] as $line) {
                $total = $total->plus($this->receiveLine($purchase, $branch, $currency, $rate, $line));
            }

            $purchase->update(['total' => $total->amount]);

            if ($type === PurchaseType::TradeIn) {
                $this->payCustomer($purchase, $branch, $total, $rate);
            }

            Audit::log('purchase.created', $purchase, null, [
                'type' => $type->value,
                'total' => $total->amount,
                'currency' => $currency->value,
            ]);

            return $purchase;
        });
    }

    /**
     * @param  Line  $line
     */
    private function receiveLine(Purchase $purchase, Branch $branch, Currency $currency, ?string $rate, array $line): Money
    {
        $product = Product::query()->with('category')->findOrFail($line['product_id']);
        $serial = $product->category->tracking === Tracking::Serial;
        $units = $serial ? ($line['units'] ?? []) : [];
        $qty = $serial ? count($units) : (int) ($line['qty'] ?? 0);

        if ($qty < 1) {
            throw new BusinessRuleException(
                'QTY_INVALID',
                $serial ? 'Kamida bitta IMEI kiriting.' : "Miqdor kamida 1 bo'lishi kerak.",
                'lines',
            );
        }

        $unitCost = Money::of($line['unit_cost'], $currency);
        $shared = [
            'sale_price' => $line['sale_price'],
            'sale_currency' => Currency::from($line['sale_currency']),
            'max_discount_pct' => $line['max_discount_pct'] ?? $product->default_max_discount_pct,
            'warranty_months' => $line['warranty_months'] ?? $product->default_warranty_months,
        ];
        $attributes = array_filter($line['attributes'] ?? [], fn ($value) => $value !== '');

        $variant = $serial ? null : $this->resolveVariant($product, $attributes, $line['barcode'] ?? null, $shared);

        $item = PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'qty' => $qty,
            'unit_cost' => $unitCost->amount,
            'attributes' => $attributes ?: null,
            ...$shared,
        ]);

        if ($variant !== null) {
            StockBatch::query()->create([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'purchase_item_id' => $item->id,
                'qty_in' => $qty,
                'qty_left' => $qty,
                'unit_cost' => $unitCost->amount,
                'cost_currency' => $currency,
                'cost_rate' => $rate,
            ]);
        } else {
            $barcodes = $this->barcodes->next($qty);

            foreach ($units as $index => $unit) {
                StockUnit::query()->create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'purchase_item_id' => $item->id,
                    'imei1' => Imei::normalize($unit['imei1']),
                    'imei2' => Imei::normalize($unit['imei2'] ?? null) ?: null,
                    'barcode' => $barcodes[$index],
                    'attributes' => $attributes ?: null,
                    'cost' => $unitCost->amount,
                    'cost_currency' => $currency,
                    'cost_rate' => $rate,
                    'status' => StockStatus::InStock,
                    ...$shared,
                ]);
            }
        }

        return $unitCost->times($qty);
    }

    /**
     * Aksesuar variantini topadi yoki yaratadi va narxini yangilaydi.
     *
     * @param  array<string, string>  $attributes
     * @param  array{sale_price: string, sale_currency: Currency, max_discount_pct: string, warranty_months: int}  $shared
     */
    private function resolveVariant(Product $product, array $attributes, ?string $barcode, array $shared): ProductVariant
    {
        $barcode = $barcode !== null ? trim($barcode) : '';

        $variant = $barcode !== ''
            ? ProductVariant::query()->where('barcode', $barcode)->first()
            : ProductVariant::query()
                ->where('product_id', $product->id)
                ->get()
                ->first(fn (ProductVariant $candidate) => ($candidate->attributes ?? []) == $attributes);

        if ($variant !== null && $variant->product_id !== $product->id) {
            throw new BusinessRuleException('BARCODE_TAKEN', 'Bu barcode boshqa mahsulotga tegishli.', 'lines');
        }

        if ($variant === null) {
            return ProductVariant::query()->create([
                'product_id' => $product->id,
                'barcode' => $barcode !== '' ? $barcode : $this->barcodes->next()[0],
                'attributes' => $attributes ?: null,
                ...$shared,
            ]);
        }

        $variant->update($shared);

        return $variant;
    }

    private function payCustomer(Purchase $purchase, Branch $branch, Money $total, ?string $rate): void
    {
        if ($purchase->customer_id === null) {
            throw new BusinessRuleException('CUSTOMER_REQUIRED', 'Mijozni tanlang.', 'customer_id');
        }

        $account = CashAccount::query()
            ->whereKey($purchase->cash_account_id)
            ->where('branch_id', $branch->id)
            ->first();

        if ($account === null) {
            throw new BusinessRuleException('CASH_ACCOUNT_REQUIRED', 'Shu filial kassasini tanlang.', 'cash_account_id');
        }

        $this->cash->withdraw($account, $total, 'trade_in', $purchase, $rate);
    }

    /**
     * Dollar ishtirok etsa, kurs bo'lishi shart: foyda keyin shu kurs bilan hisoblanadi.
     *
     * @param  list<Line>  $lines
     */
    private function assertRateAvailable(Currency $currency, array $lines, ?string $rate): void
    {
        $usesUsd = $currency === Currency::USD
            || collect($lines)->contains(fn (array $line) => $line['sale_currency'] === Currency::USD->value);

        if ($usesUsd && $rate === null) {
            throw new BusinessRuleException(
                'RATE_MISSING',
                'Dollar kursi kiritilmagan. Avval Sozlamalarda kursni kiriting.',
                'currency',
            );
        }
    }

    /**
     * IMEI to'g'ri, kirim ichida takrorlanmagan va do'konda hali yo'qligini tekshiradi.
     *
     * @param  list<Line>  $lines
     */
    private function assertImeisAreFree(array $lines): void
    {
        $imeis = [];

        foreach ($lines as $line) {
            foreach ($line['units'] ?? [] as $unit) {
                foreach ([$unit['imei1'], $unit['imei2'] ?? null] as $value) {
                    $imei = Imei::normalize($value);

                    if ($imei === '') {
                        continue;
                    }

                    if (! Imei::isValid($imei)) {
                        throw new BusinessRuleException('IMEI_INVALID', "IMEI noto'g'ri: {$imei}", 'lines');
                    }

                    if (in_array($imei, $imeis, true)) {
                        throw new BusinessRuleException('IMEI_DUPLICATE', "IMEI ikki marta kiritilgan: {$imei}", 'lines');
                    }

                    $imeis[] = $imei;
                }
            }
        }

        if ($imeis === []) {
            return;
        }

        $taken = StockUnit::query()
            ->where(fn ($query) => $query->whereIn('imei1', $imeis)->orWhereIn('imei2', $imeis))
            ->first();

        if ($taken !== null) {
            $imei = in_array($taken->imei1, $imeis, true) ? $taken->imei1 : $taken->imei2;

            throw new BusinessRuleException('IMEI_TAKEN', "Bu IMEI do'konda allaqachon bor: {$imei}", 'lines');
        }
    }
}
