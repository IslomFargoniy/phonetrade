<?php

namespace App\Http\Controllers\Api;

use App\Actions\Cash\SweepCash;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Services\Cash\CashService;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Do'kon egasi uchun: hisobot ko'rsatkichlari va kassalar (iOS ilova).
 */
class OwnerController extends Controller
{
    public function summary(Request $request, ReportService $reports): JsonResponse
    {
        $today = CarbonImmutable::today();
        $from = match ($request->query('period')) {
            'day' => $today,
            'week' => $today->startOfWeek(),
            default => $today->startOfMonth(),
        };
        $branchId = $request->integer('branch') ?: null;

        $summary = $reports->summary($from, $today, $branchId);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
            'sales_count' => $summary['sales_count'],
            'revenue' => $summary['revenue']->amount,
            'gross_profit' => $summary['gross_profit']->amount,
            'expenses' => $summary['expenses']->amount,
            'net_profit' => $summary['net_profit']->amount,
            'installment_income' => $summary['installment_income']->amount,
            'installment_payments' => $summary['installment_payments'],
            'active_debt' => $summary['active_debt']->amount,
            'active_plans' => $summary['active_plans'],
            'expected_profit' => array_map(fn (array $row) => [
                'month' => $row['month'], 'label' => $row['label'], 'profit' => $row['profit']->amount,
            ], $reports->expectedProfit(6, $branchId)),
        ]);
    }

    public function cash(Request $request, CashService $cash): JsonResponse
    {
        $branch = $this->branch($request);

        $present = fn (CashAccount $account) => [
            'id' => $account->id,
            'type' => $account->type->value,
            'label' => $account->type->label(),
            'uzs' => $cash->balance($account, Currency::UZS)->amount,
            'usd' => $cash->balance($account, Currency::USD)->amount,
        ];

        return response()->json([
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'branch_accounts' => CashAccount::query()->where('branch_id', $branch->id)->orderBy('id')->get()->map($present)->values(),
            'admin_accounts' => CashAccount::query()->whereNull('branch_id')->orderBy('id')->get()->map($present)->values(),
        ]);
    }

    public function sweep(Request $request, SweepCash $sweepCash): JsonResponse
    {
        $sweepCash->handle($this->branch($request), $this->user($request));

        return response()->json(['message' => "Kassa bo'shatildi."], 201);
    }
}
