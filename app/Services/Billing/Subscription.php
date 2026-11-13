<?php

namespace App\Services\Billing;

use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Obuna holati va tarif limitlari.
 *
 * Muddat tugagach GRACE_DAYS kun imtiyozli davr: hamma narsa ishlaydi, egasi ogohlantiriladi.
 * Undan keyin do'kon faqat o'qish rejimiga o'tadi — ma'lumot ko'rinadi, lekin yozib bo'lmaydi.
 */
class Subscription
{
    public const GRACE_DAYS = 5;

    public const STATE_UNLIMITED = 'unlimited';

    public const STATE_ACTIVE = 'active';

    public const STATE_GRACE = 'grace';

    public const STATE_EXPIRED = 'expired';

    public function state(Tenant $tenant): string
    {
        if ($tenant->paid_until === null) {
            return self::STATE_UNLIMITED;
        }

        $today = CarbonImmutable::today();
        $until = CarbonImmutable::parse($tenant->paid_until)->startOfDay();

        return match (true) {
            $today->lessThanOrEqualTo($until) => self::STATE_ACTIVE,
            $today->lessThanOrEqualTo($until->addDays(self::GRACE_DAYS)) => self::STATE_GRACE,
            default => self::STATE_EXPIRED,
        };
    }

    public function isReadOnly(Tenant $tenant): bool
    {
        return $this->state($tenant) === self::STATE_EXPIRED;
    }

    /**
     * To'langan muddat tugashiga qolgan kunlar (manfiy — o'tib ketgan). Muddat yo'q bo'lsa null.
     */
    public function daysLeft(Tenant $tenant): ?int
    {
        if ($tenant->paid_until === null) {
            return null;
        }

        return (int) CarbonImmutable::today()->diffInDays(CarbonImmutable::parse($tenant->paid_until)->startOfDay(), false);
    }

    /**
     * Yozish imkoni to'liq yopiladigan kun (imtiyozli davrning oxiri).
     */
    public function readOnlyFrom(Tenant $tenant): ?CarbonImmutable
    {
        return $tenant->paid_until === null
            ? null
            : CarbonImmutable::parse($tenant->paid_until)->startOfDay()->addDays(self::GRACE_DAYS + 1);
    }

    /**
     * Yangi faol filial qo'shishdan oldin tekshiriladi (do'kon konteksti o'rnatilgan holda).
     */
    public function ensureCanAddBranch(Tenant $tenant): void
    {
        $limit = $tenant->plan?->max_branches;

        if ($limit !== null && Branch::query()->where('is_active', true)->count() >= $limit) {
            throw new BusinessRuleException(
                'PLAN_LIMIT_REACHED',
                "Tarifingizda {$limit} ta filialga ruxsat bor. Ko'paytirish uchun tarifni almashtiring.",
                'name',
            );
        }
    }

    /**
     * Yangi faol xodim (sotuvchi) qo'shishdan oldin tekshiriladi. Egasi limitga kirmaydi.
     */
    public function ensureCanAddUser(Tenant $tenant): void
    {
        $limit = $tenant->plan?->max_users;

        if ($limit !== null && User::query()->where('role', Role::Seller)->where('is_active', true)->count() >= $limit) {
            throw new BusinessRuleException(
                'PLAN_LIMIT_REACHED',
                "Tarifingizda {$limit} ta xodimga ruxsat bor. Ko'paytirish uchun tarifni almashtiring.",
                'name',
            );
        }
    }

    /**
     * To'lovni yozadi va muddatni uzaytiradi. Muddat hali tugamagan bo'lsa uning ustiga,
     * tugagan bo'lsa bugundan boshlab qo'shiladi.
     */
    public function recordPayment(Tenant $tenant, Plan $plan, int $months, string $amount, ?string $note, ?User $by): SubscriptionPayment
    {
        return DB::transaction(function () use ($tenant, $plan, $months, $amount, $note, $by): SubscriptionPayment {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $today = CarbonImmutable::today();
            $current = $tenant->paid_until !== null ? CarbonImmutable::parse($tenant->paid_until)->startOfDay() : null;

            $from = $current !== null && $current->greaterThanOrEqualTo($today) ? $current->addDay() : $today;
            $to = $from->addMonthsNoOverflow($months)->subDay();

            $payment = SubscriptionPayment::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'months' => $months,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'note' => $note,
                'created_by' => $by?->id,
            ]);

            $tenant->forceFill(['plan_id' => $plan->id, 'paid_until' => $to->toDateString()])->save();

            return $payment;
        });
    }
}
