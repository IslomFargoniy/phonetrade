<?php

namespace App\Http\Requests\Sales;

use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Savat: oldindan ko'rish va savdoni yopish bir xil qoidalar bilan tekshiriladi.
 */
class CartRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('customer_phone')) && $this->input('customer_phone') !== '') {
            $this->merge(['customer_phone' => Phone::normalize($this->input('customer_phone')) ?? $this->input('customer_phone')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.stock_unit_id' => ['nullable', 'integer', Rule::exists(StockUnit::class, 'id')->where('tenant_id', $tenantId)],
            'items.*.variant_id' => ['nullable', 'required_without:items.*.stock_unit_id', 'integer', Rule::exists(ProductVariant::class, 'id')->where('tenant_id', $tenantId)],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['nullable', 'array', 'max:6'],
            'payments.*.method' => ['required', Rule::enum(CashAccountType::class)],
            'payments.*.currency' => ['required', Rule::enum(Currency::class)],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],

            'customer_phone' => ['nullable', 'string', 'regex:'.Phone::PATTERN],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'Savat',
            'items.*.discount' => 'Chegirma',
            'items.*.qty' => 'Miqdor',
            'payments.*.amount' => "To'lov summasi",
            'customer_phone' => 'Mijoz telefoni',
        ];
    }

    /**
     * @return list<array{stock_unit_id: int|null, variant_id: int|null, qty: int, discount: string}>
     */
    public function items(): array
    {
        $items = [];

        foreach (array_values((array) $this->validated('items')) as $item) {
            $items[] = [
                'stock_unit_id' => isset($item['stock_unit_id']) ? (int) $item['stock_unit_id'] : null,
                'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                'qty' => (int) ($item['qty'] ?? 1),
                'discount' => isset($item['discount']) && $item['discount'] !== '' ? (string) $item['discount'] : '0',
            ];
        }

        return $items;
    }

    /**
     * @return list<array{method: string, currency: string, amount: string}>
     */
    public function payments(): array
    {
        $payments = [];

        foreach (array_values((array) $this->validated('payments', [])) as $payment) {
            $amount = (string) ($payment['amount'] ?? '');

            if ($amount === '' || (float) $amount <= 0) {
                continue;
            }

            $payments[] = [
                'method' => (string) $payment['method'],
                'currency' => (string) $payment['currency'],
                'amount' => $amount,
            ];
        }

        return $payments;
    }
}
