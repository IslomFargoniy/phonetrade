<?php

namespace App\Http\Controllers\Reports;

use App\Enums\CashAccountType;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\InstallmentPresenter;
use App\Models\Branch;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Services\Reports\ReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Egasi uchun hisobotlar. Barcha summalar so'mda.
 */
class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        [$period, $from, $to] = $this->period($request);
        $branchId = $this->branchFilter($request);

        $summary = $reports->summary($from, $to, $branchId);
        $stock = $reports->stock($branchId);
        $expected = $reports->expectedProfit(6, $branchId);
        $maxExpected = collect($expected)->reduce(fn (Money $carry, array $row) => $row['profit']->greaterThan($carry) ? $row['profit'] : $carry, Money::uzs(0));

        return Inertia::render('reports/index', [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branchId' => $branchId,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'kpis' => [
                ['label' => 'Savdo tushumi', 'value' => $summary['revenue']->format(), 'sub' => "{$summary['sales_count']} ta savdo"],
                ['label' => 'Yalpi foyda', 'value' => $summary['gross_profit']->format(), 'sub' => "to'liq savdo + nasiya to'lovlari"],
                ['label' => 'Sof foyda', 'value' => $summary['net_profit']->format(), 'sub' => 'xarajat '.$summary['expenses']->format()],
                ['label' => 'Nasiya tushumi', 'value' => $summary['installment_income']->format(), 'sub' => "{$summary['installment_payments']} ta to'lov"],
                ['label' => 'Faol nasiya qarzi', 'value' => $summary['active_debt']->format(), 'sub' => "{$summary['active_plans']} ta shartnoma"],
            ],
            'expected' => array_map(fn (array $row) => [
                'label' => $row['label'],
                // mln so'mda, bir kasr xona bilan
                'value' => number_format((float) $row['profit']->amount / 1_000_000, 1, ',', ' '),
                'percent' => $maxExpected->isPositive() ? (int) round((float) $row['profit']->amount / (float) $maxExpected->amount * 100) : 0,
            ], $expected),
            'cash' => [
                'branch' => $this->cashBlock($reports->cash($from, $to, $branchId ?? $this->branch($request)->id)),
                'admin' => $this->cashBlock($reports->cash($from, $to, adminOnly: true)),
            ],
            'debtors' => InstallmentPlan::query()
                ->with(['customer', 'sale.items.unit.product:id,image_path', 'sale.items.unit.photos', 'sale.items.variant.product:id,image_path', 'schedule' => fn ($rows) => $rows->orderBy('no'), 'payments'])
                ->where('status', PlanStatus::Active)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereHas('schedule', fn ($rows) => $rows->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', '<=', now()->addDays(7)->toDateString()))
                ->limit(50)
                ->get()
                ->map(fn (InstallmentPlan $plan) => InstallmentPresenter::plan($plan))
                ->sortBy(fn (array $plan) => ['late' => 0, 'today' => 1][$plan['state']] ?? 2)
                ->values(),
            'sellers' => array_map(fn (array $row) => [
                'name' => $row['name'], 'sales' => $row['sales'], 'revenue' => $row['revenue']->format(), 'profit' => $row['profit']->format(),
            ], $reports->salesBySeller($from, $to, $branchId)),
            'products' => array_map(fn (array $row) => [
                'name' => $row['name'], 'qty' => $row['qty'], 'revenue' => $row['revenue']->format(), 'profit' => $row['profit']->format(),
            ], $reports->salesByProduct($from, $to, $branchId)),
            'stock' => [
                'units' => $stock['units'],
                'accessories' => $stock['accessories'],
                'cost' => $stock['cost']->format(),
                'value' => $stock['value']->format(),
                'aging' => $stock['aging'],
            ],
            'purchases' => array_map(fn (array $row) => [
                'name' => $row['name'], 'type_label' => $row['type_label'], 'purchases' => $row['purchases'], 'total' => $row['total']->format(),
            ], $reports->purchases($from, $to, $branchId)),
        ]);
    }

    /**
     * Savdolar ro'yxatini Excel ochadigan CSV qilib beradi.
     */
    public function export(Request $request, ReportService $reports): StreamedResponse
    {
        [, $from, $to] = $this->period($request);
        $branchId = $this->branchFilter($request);

        $sales = Sale::query()
            ->with(['items', 'user:id,name', 'customer:id,full_name', 'branch:id,name'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($sales, $reports): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // Excel UTF-8 ni to'g'ri o'qishi uchun BOM
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Chek', 'Sana', 'Filial', 'Sotuvchi', 'Mijoz', 'Turi', 'Tovarlar', "Jami (so'm)", "Chegirma (so'm)", "Foyda (so'm)", 'Holat'], ';');

            foreach ($sales as $sale) {
                $profit = $sale->items->reduce(
                    fn (Money $carry, $item) => $carry->plus($reports->uzs($item->profit, $sale->currency, $sale->rate)),
                    Money::uzs(0),
                );

                fputcsv($out, [
                    $sale->number,
                    $sale->created_at?->format('d.m.Y H:i'),
                    $sale->branch->name,
                    $sale->user->name ?? '',
                    $sale->customer->full_name ?? '',
                    $sale->type->label(),
                    $sale->items->map(fn ($item) => $item->qty > 1 ? "{$item->name} × {$item->qty}" : $item->name)->join(', '),
                    $reports->uzs($sale->total, $sale->currency, $sale->rate)->amount,
                    $reports->uzs($sale->discount, $sale->currency, $sale->rate)->amount,
                    $sale->type->value === 'full' ? $profit->amount : '',
                    $sale->status->label(),
                ], ';');
            }

            fclose($out);
        }, "savdolar-{$from->toDateString()}_{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $period = in_array($request->query('period'), ['day', 'week', 'month', 'custom'], true) ? (string) $request->query('period') : 'month';
        $today = CarbonImmutable::today();

        if ($period === 'custom') {
            $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

            return [$period, CarbonImmutable::parse((string) $data['from']), CarbonImmutable::parse((string) $data['to'])];
        }

        return match ($period) {
            'day' => [$period, $today, $today],
            'week' => [$period, $today->startOfWeek(), $today],
            default => [$period, $today->startOfMonth(), $today],
        };
    }

    private function branchFilter(Request $request): ?int
    {
        $branchId = $request->integer('branch');

        return $branchId > 0 && Branch::query()->whereKey($branchId)->exists() ? $branchId : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, uzs: string, usd: string}>
     */
    private function cashBlock(array $rows): array
    {
        $block = [];

        foreach (CashAccountType::cases() as $type) {
            $uzs = Money::uzs(0);
            $usd = Money::usd(0);

            foreach ($rows as $row) {
                if ($row['type'] !== $type->value) {
                    continue;
                }

                if ($row['currency']->value === 'UZS') {
                    $uzs = $uzs->plus($row['closing']);
                } else {
                    $usd = $usd->plus($row['closing']);
                }
            }

            $block[] = ['label' => $type->label(), 'uzs' => $uzs->format(), 'usd' => $usd->format()];
        }

        return $block;
    }
}
