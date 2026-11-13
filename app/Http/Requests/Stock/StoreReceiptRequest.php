<?php

namespace App\Http\Requests\Stock;

use App\Enums\Currency;
use App\Enums\PurchaseType;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceiptRequest extends FormRequest
{
    /**
     * Sotuvchi faqat mijozdan telefon sotib ola oladi; qolgan kirim turlari egasiga.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ($user->isOwner() || $this->input('type') === PurchaseType::TradeIn->value);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('customer_phone'))) {
            $this->merge(['customer_phone' => Phone::normalize($this->input('customer_phone')) ?? $this->input('customer_phone')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $tradeIn = PurchaseType::TradeIn->value;

        return [
            'type' => ['required', Rule::enum(PurchaseType::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'supplier_id' => [
                'nullable', 'required_if:type,'.PurchaseType::Supplier->value, 'integer',
                Rule::exists(Supplier::class, 'id')->where('tenant_id', $tenantId),
            ],
            'customer_phone' => ['nullable', "required_if:type,{$tradeIn}", 'string', 'regex:'.Phone::PATTERN],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'cash_account_id' => [
                'nullable', "required_if:type,{$tradeIn}", 'integer',
                Rule::exists(CashAccount::class, 'id')->where('tenant_id', $tenantId),
            ],
            'note' => ['nullable', 'string', 'max:255'],

            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('tenant_id', $tenantId)],
            'lines.*.attributes' => ['nullable', 'array'],
            'lines.*.attributes.*' => ['nullable', 'string', 'max:100'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.sale_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.sale_currency' => ['required', Rule::enum(Currency::class)],
            'lines.*.max_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'lines.*.barcode' => ['nullable', 'string', 'max:64'],
            'lines.*.units' => ['nullable', 'array', 'max:500'],
            'lines.*.units.*.imei1' => ['required', 'string', 'max:20'],
            'lines.*.units.*.imei2' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Tekshirilgan ma'lumotni ReceiveStock kutadigan aniq ko'rinishga keltiradi.
     *
     * @return array{
     *     type: string,
     *     currency: string,
     *     supplier_id: int|null,
     *     customer_id: int|null,
     *     cash_account_id: int|null,
     *     note: string|null,
     *     lines: list<array{
     *         product_id: int,
     *         attributes: array<string, string>,
     *         unit_cost: string,
     *         sale_price: string,
     *         sale_currency: string,
     *         max_discount_pct: string|null,
     *         warranty_months: int|null,
     *         units: list<array{imei1: string, imei2: string|null}>,
     *         qty: int|null,
     *         barcode: string|null
     *     }>
     * }
     */
    public function payload(): array
    {
        $data = $this->validated();
        $lines = [];

        foreach (array_values((array) $data['lines']) as $line) {
            $attributes = [];

            foreach ((array) ($line['attributes'] ?? []) as $name => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $attributes[(string) $name] = trim($value);
                }
            }

            $units = [];

            foreach (array_values((array) ($line['units'] ?? [])) as $unit) {
                $imei2 = trim((string) ($unit['imei2'] ?? ''));
                $units[] = ['imei1' => trim((string) $unit['imei1']), 'imei2' => $imei2 !== '' ? $imei2 : null];
            }

            $barcode = trim((string) ($line['barcode'] ?? ''));

            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'attributes' => $attributes,
                'unit_cost' => (string) $line['unit_cost'],
                'sale_price' => (string) $line['sale_price'],
                'sale_currency' => (string) $line['sale_currency'],
                'max_discount_pct' => isset($line['max_discount_pct']) ? (string) $line['max_discount_pct'] : null,
                'warranty_months' => isset($line['warranty_months']) ? (int) $line['warranty_months'] : null,
                'units' => $units,
                'qty' => isset($line['qty']) ? (int) $line['qty'] : null,
                'barcode' => $barcode !== '' ? $barcode : null,
            ];
        }

        return [
            'type' => (string) $data['type'],
            'currency' => (string) $data['currency'],
            'supplier_id' => isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            'customer_id' => null,
            'cash_account_id' => isset($data['cash_account_id']) ? (int) $data['cash_account_id'] : null,
            'note' => isset($data['note']) ? (string) $data['note'] : null,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'Yetkazib beruvchi',
            'customer_phone' => 'Mijoz telefoni',
            'customer_name' => 'Mijoz ismi',
            'cash_account_id' => 'Kassa',
            'lines.*.product_id' => 'Mahsulot',
            'lines.*.unit_cost' => 'Tannarx',
            'lines.*.sale_price' => 'Sotuv narxi',
            'lines.*.max_discount_pct' => 'Max chegirma',
            'lines.*.warranty_months' => 'Kafolat',
            'lines.*.qty' => 'Miqdor',
            'lines.*.units.*.imei1' => 'IMEI 1',
        ];
    }
}
