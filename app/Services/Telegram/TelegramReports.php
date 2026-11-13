<?php

namespace App\Services\Telegram;

use App\Enums\PlanStatus;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use App\Support\Money;
use App\Support\Phone;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Bot xabarlari matnini tayyorlaydi. Raqamlar web hisobotlaridagi servisdan olinadi.
 */
class TelegramReports
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ReportService $reports,
    ) {}

    /**
     * Egasi uchun bugungi savdo va foyda.
     */
    public function daily(Tenant $tenant): string
    {
        return $this->context->runFor($tenant, function () use ($tenant): string {
            $today = CarbonImmutable::today();
            $summary = $this->reports->summary($today, $today);
            $late = $this->latePlans();

            $lines = [
                '<b>'.e($tenant->name).'</b> — '.$today->format('d.m.Y'),
                '',
                "Savdolar: <b>{$summary['sales_count']} ta</b>",
                'Tushum: <b>'.$summary['revenue']->format().'</b>',
                'Yalpi foyda: <b>'.$summary['gross_profit']->format().'</b>',
                'Xarajatlar: '.$summary['expenses']->format(),
                'Sof foyda: <b>'.$summary['net_profit']->format().'</b>',
                '',
                "Nasiya to'lovlari: {$summary['installment_payments']} ta, ".$summary['installment_income']->format(),
                "Faol nasiya: {$summary['active_plans']} ta, qarz ".$summary['active_debt']->format(),
                'Muddati o\'tgan: '.($late === [] ? "yo'q" : count($late).' ta — /qarzdorlar'),
            ];

            return implode("\n", $lines);
        });
    }

    /**
     * Egasi uchun muddati o'tgan to'lovlar ro'yxati.
     */
    public function overdue(Tenant $tenant): string
    {
        return $this->context->runFor($tenant, function (): string {
            $late = $this->latePlans();

            if ($late === []) {
                return "Muddati o'tgan to'lov yo'q.";
            }

            $lines = ["<b>Muddati o'tgan to'lovlar — ".count($late).' ta</b>', ''];

            foreach (array_slice($late, 0, 30) as $row) {
                $lines[] = sprintf(
                    '%s, %s — %s, %d kun kechikkan',
                    e($row['customer']),
                    Phone::format($row['phone']),
                    $row['amount']->format(),
                    $row['days'],
                );
            }

            if (count($late) > 30) {
                $lines[] = '… va yana '.(count($late) - 30).' ta. To\'liq ro\'yxat web panelda.';
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Mijoz uchun: ulangan barcha do'konlardagi qarzlari.
     */
    public function customerDebts(int $chatId): string
    {
        $customers = Customer::query()->where('telegram_chat_id', $chatId)->get();

        if ($customers->isEmpty()) {
            return 'Siz hali ulanmagansiz. Pastdagi tugma orqali telefon raqamingizni yuboring.';
        }

        $plans = InstallmentPlan::query()
            ->with(['schedule' => fn ($rows) => $rows->orderBy('no'), 'sale.items'])
            ->whereIn('customer_id', $customers->pluck('id'))
            ->where('status', PlanStatus::Active)
            ->get();

        if ($plans->isEmpty()) {
            return "Sizda faol muddatli to'lov yo'q.";
        }

        $tenants = Tenant::query()->whereIn('id', $plans->pluck('tenant_id')->unique())->pluck('name', 'id');
        $lines = [];

        foreach ($plans as $plan) {
            $next = $plan->schedule->first(fn (InstallmentScheduleItem $row) => $this->unpaid($row, $plan)->isPositive());

            $lines[] = '<b>'.e((string) $tenants[$plan->tenant_id]).'</b> — '.e($plan->sale->items->pluck('name')->join(', '));
            $lines[] = 'Qolgan qarz: <b>'.$plan->remaining()->format().'</b>';

            if ($next !== null) {
                $lines[] = "Navbatdagi to'lov: ".$this->unpaid($next, $plan)->format().', '.$next->due_date->format('d.m.Y');
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * Mijozga to'lov eslatmasi.
     *
     * @param  int  $daysLeft  to'lov kunigacha qolgan kunlar; manfiy — kechikkan
     */
    public function reminder(InstallmentScheduleItem $row, InstallmentPlan $plan, string $store, int $daysLeft): string
    {
        $amount = $this->unpaid($row, $plan)->format();
        $date = $row->due_date->format('d.m.Y');

        $lead = match (true) {
            $daysLeft > 0 => "To'lov kuniga {$daysLeft} kun qoldi.",
            $daysLeft === 0 => "Bugun to'lov kuni.",
            default => "To'lov muddati ".abs($daysLeft).' kun oldin o\'tgan.',
        };

        return implode("\n", [
            '<b>'.e($store).'</b>',
            $lead,
            '',
            "To'lov: <b>{$amount}</b>, {$date}",
            'Qolgan qarz: '.$plan->remaining()->format(),
        ]);
    }

    public function unpaid(InstallmentScheduleItem $row, InstallmentPlan $plan): Money
    {
        return Money::of($row->amount, $plan->currency)->minus(Money::of($row->paid_amount, $plan->currency));
    }

    /**
     * Joriy do'kondagi kechikkan shartnomalar: eng eski to'lanmagan oylik bo'yicha.
     *
     * @return list<array{customer: string, phone: string, amount: Money, days: int}>
     */
    private function latePlans(): array
    {
        $today = CarbonImmutable::today();
        $rows = [];

        $plans = InstallmentPlan::query()
            ->with(['customer', 'schedule' => fn ($schedule) => $schedule->orderBy('no')])
            ->where('status', PlanStatus::Active)
            ->whereHas('schedule', fn ($schedule) => $schedule
                ->whereColumn('paid_amount', '<', 'amount')
                ->whereDate('due_date', '<', $today->toDateString()))
            ->get();

        foreach ($plans as $plan) {
            $late = $plan->schedule->filter(
                fn (InstallmentScheduleItem $row) => $row->due_date->isBefore($today) && $this->unpaid($row, $plan)->isPositive(),
            );

            if ($late->isEmpty()) {
                continue;
            }

            $rows[] = [
                'customer' => $plan->customer->full_name,
                'phone' => $plan->customer->phone,
                'amount' => $late->reduce(
                    fn (Money $carry, InstallmentScheduleItem $row) => $carry->plus($this->unpaid($row, $plan)),
                    Money::zero($plan->currency),
                ),
                'days' => (int) abs($today->diffInDays($late->first()->due_date)),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['days'] <=> $a['days']);

        return $rows;
    }
}
