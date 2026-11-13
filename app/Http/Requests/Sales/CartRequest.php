<?php

namespace App\Http\Requests\Sales;

use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Models\InstallmentRate;
use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Services\Installments\InstallmentTerms;
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
        foreach (['customer_phone', 'customer_phone2'] as $field) {
            if (is_string($this->input($field)) && $this->input($field) !== '') {
                $this->merge([$field => Phone::normalize($this->input($field)) ?? $this->input($field)]);
            }
        }

        if (is_string($this->input('passport_series'))) {
            $this->merge(['passport_series' => strtoupper(trim($this->input('passport_series')))]);
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

            'mode' => ['nullable', Rule::in(['full', 'installment'])],
            'installment' => ['nullable', 'required_if:mode,installment', 'array'],
            'installment.currency' => ['nullable', 'required_if:mode,installment', Rule::enum(Currency::class)],
            'installment.months' => ['nullable', 'required_if:mode,installment', 'integer', Rule::in(array_keys(InstallmentRate::DEFAULTS))],
            'installment.pay_day' => ['nullable', 'required_if:mode,installment', 'integer', 'min:1', 'max:28'],
            'installment.down_payment' => ['nullable', 'numeric', 'min:0'],

            // To'liq to'lovda mijoz ixtiyoriy; muddatli savdoda karta to'liq to'ldiriladi.
            'customer_phone' => ['nullable', $this->routeIs('sales.store') ? 'required_if:mode,installment' : 'nullable', 'string', 'regex:'.Phone::PATTERN],
            'customer_name' => ['nullable', $this->requiredForContract(), 'string', 'max:255'],
            'customer_phone2' => ['nullable', 'string', 'regex:'.Phone::PATTERN],
            'passport_series' => ['nullable', $this->requiredForContract(), 'string', 'regex:/^[A-Za-z]{2}$/'],
            'passport_number' => ['nullable', $this->requiredForContract(), 'string', 'regex:/^\d{7}$/'],
            'pinfl' => ['nullable', $this->requiredForContract(), 'string', 'regex:/^\d{14}$/'],
            'customer_address' => ['nullable', $this->requiredForContract(), 'string', 'max:255'],
            'passport_files' => ['nullable', 'array', 'max:5'],
            'passport_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }

    /**
     * Mijoz kartasi maydonlari faqat muddatli savdoni yopishda majburiy
     * (oldindan ko'rishda hali to'ldirilmagan bo'lishi mumkin).
     */
    private function requiredForContract(): string
    {
        return $this->routeIs('sales.store') ? 'required_if:mode,installment' : 'nullable';
    }

    public function isInstallment(): bool
    {
        return $this->input('mode') === 'installment';
    }

    public function terms(): ?InstallmentTerms
    {
        if (! $this->isInstallment()) {
            return null;
        }

        return new InstallmentTerms(
            currency: Currency::from((string) $this->input('installment.currency')),
            months: (int) $this->input('installment.months'),
            payDay: (int) $this->input('installment.pay_day'),
            downPayment: (string) ($this->input('installment.down_payment') ?: '0'),
        );
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
            'customer_phone2' => "Qo'shimcha telefon",
            'customer_name' => 'F.I.Sh',
            'passport_series' => 'Pasport seriyasi',
            'passport_number' => 'Pasport raqami',
            'pinfl' => 'JSHSHIR',
            'customer_address' => 'Manzil',
            'passport_files' => 'Pasport rasmi',
            'installment.months' => 'Muddat',
            'installment.pay_day' => "To'lov kuni",
            'installment.down_payment' => "Boshlang'ich to'lov",
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
