<?php

namespace App\Http\Presenters;

use App\Enums\PlanStatus;
use App\Enums\SaleType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Sales\CartLine;
use App\Services\Sales\CartTotals;
use App\Support\Money;
use App\Support\Phone;

/**
 * Savdo ma'lumotini sahifaga beradi. Tannarx va foyda faqat egasiga qo'shiladi.
 */
final class SalePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'date' => $sale->created_at?->format('d.m.Y H:i'),
            'type' => $sale->type->value,
            'type_label' => $sale->type->label(),
            'status' => $sale->status->value,
            'status_label' => $sale->status->label(),
            'total_label' => Money::of($sale->total, $sale->currency)->format(),
            'seller' => $sale->user->name ?? null,
            'customer' => $sale->customer->full_name ?? null,
            'items' => $sale->items->map(fn (SaleItem $item) => $item->qty > 1 ? "{$item->name} × {$item->qty}" : $item->name)->join(', '),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Sale $sale, User $viewer): array
    {
        $currency = $sale->currency;
        $money = fn (string $amount) => Money::of($amount, $currency)->format();

        $data = [
            ...self::summary($sale),
            'branch' => $sale->branch->name,
            'currency' => $currency->value,
            'rate' => $sale->rate,
            'subtotal_label' => $money($sale->subtotal),
            'discount_label' => $money($sale->discount),
            'has_discount' => Money::of($sale->discount, $currency)->isPositive(),
            'change_label' => $money($sale->change),
            'has_change' => Money::of($sale->change, $currency)->isPositive(),
            'customer_phone' => isset($sale->customer->phone) ? Phone::format($sale->customer->phone) : null,
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'qty' => $item->qty,
                'returned_qty' => $item->returned_qty,
                'returnable_qty' => $item->qty - $item->returned_qty,
                'imei' => $item->unit->imei1 ?? null,
                'barcode' => $item->unit->barcode ?? $item->variant->barcode ?? null,
                'attributes' => StockPresenter::attributes($item->unit->attributes ?? $item->variant->attributes ?? null),
                'price_label' => Money::of($item->price, $item->currency)->format(),
                'discount_label' => Money::of($item->discount, $item->currency)->format(),
                'has_discount' => Money::of($item->discount, $item->currency)->isPositive(),
                'final_label' => $money($item->final_price),
                'warranty_until' => $item->warranty_until?->format('d.m.Y'),
                ...($viewer->isOwner() && $sale->type === SaleType::Full ? [
                    'cost_label' => $money($item->cost_snapshot),
                    'profit_label' => $money($item->profit),
                ] : []),
            ]),
            'payments' => $sale->payments->map(fn (SalePayment $payment) => [
                'id' => $payment->id,
                'label' => $payment->method->label().' · '.$payment->currency->label(),
                'amount_label' => Money::of($payment->amount, $payment->currency)->format(),
            ]),
        ];

        if ($viewer->isOwner()) {
            $profit = $sale->items->reduce(
                fn (Money $carry, SaleItem $item) => $carry->plus(Money::of($item->profit, $currency)),
                Money::zero($currency),
            );
            $reversed = $sale->returns->reduce(
                fn (Money $carry, $return) => $carry->plus(Money::of($return->profit_reversed, $currency)),
                Money::zero($currency),
            );

            $data['profit_label'] = $profit->minus($reversed)->format();

            // Nasiyada foyda pul tushgan sari tan olinadi: shu paytgacha tushgan to'lovlardagi ulush ko'rsatiladi.
            if ($sale->type === SaleType::Installment && $sale->plan !== null) {
                $plan = $sale->plan;
                $recognised = $plan->status === PlanStatus::Cancelled
                    ? Money::zero($plan->currency)
                    : Money::of((string) $plan->payments()->sum('profit_amount'), $plan->currency);

                $data['profit_label'] = $recognised->format();
                $data['profit_note'] = sprintf(
                    'Shartnoma bo\'yicha jami %s; pul tushgan sari tan olinadi',
                    Money::of($plan->total_profit, $plan->currency)->format(),
                );
            }
        }

        return $data;
    }

    /**
     * Kassa oynasidagi savat hisobi.
     *
     * @return array<string, mixed>
     */
    public static function totals(CartTotals $totals, ?string $rate): array
    {
        return [
            'currency' => $totals->currency->value,
            'rate' => $rate,
            'mixed' => collect($totals->lines)->contains(fn (CartLine $line) => $line->price->currency !== $totals->currency),
            'subtotal' => $totals->subtotal->amount,
            'subtotal_label' => $totals->subtotal->format(),
            'discount' => $totals->discount->amount,
            'discount_label' => $totals->discount->format(),
            'total' => $totals->total->amount,
            'total_label' => $totals->total->format(),
            'lines' => array_map(fn (CartLine $line) => [
                'gross_label' => $line->gross->format(),
                'net_label' => $line->net->format(),
                'final_label' => $line->final->format(),
                'max_discount' => $line->maxDiscount->amount,
                'max_discount_label' => $line->maxDiscount->format(),
            ], $totals->lines),
        ];
    }
}
