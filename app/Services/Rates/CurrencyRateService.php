<?php

namespace App\Services\Rates;

use App\Models\CurrencyRate;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;

/**
 * Joriy do'konning dollar kursi bilan ishlaydi. Do'kon konteksti o'rnatilgan bo'lishi kerak.
 */
class CurrencyRateService
{
    /**
     * Berilgan kunda amal qiladigan kurs: o'sha kunniki, bo'lmasa undan oldingi eng oxirgisi.
     */
    public function current(?Carbon $date = null): ?CurrencyRate
    {
        return CurrencyRate::query()
            ->whereDate('date', '<=', ($date ?? now())->toDateString())
            ->orderByDesc('date')
            ->first();
    }

    /**
     * Egasi bugungi kursni o'zi belgilaydi. Avtomatik olingan kursning o'rniga yoziladi.
     */
    public function setManual(string $rate, User $user): CurrencyRate
    {
        $previous = $this->current();

        // Sana ustuni bazaga qarab vaqt bilan saqlanishi mumkin, shuning uchun whereDate bilan qidiriladi.
        $record = CurrencyRate::query()->whereDate('date', now()->toDateString())->first()
            ?? new CurrencyRate(['date' => now()->toDateString()]);

        $record->fill([
            'usd_uzs' => $rate,
            'source' => CurrencyRate::SOURCE_MANUAL,
            'user_id' => $user->id,
        ])->save();

        Audit::log('currency_rate.set', $record, ['usd_uzs' => $previous?->usd_uzs], ['usd_uzs' => $record->usd_uzs]);

        return $record;
    }

    /**
     * Markaziy bank kursini yozadi. Bugungi kurs allaqachon bo'lsa (masalan, egasi kiritgan), tegilmaydi.
     */
    public function storeFromCbu(string $rate): ?CurrencyRate
    {
        $today = now()->toDateString();

        if (CurrencyRate::query()->whereDate('date', $today)->exists()) {
            return null;
        }

        return CurrencyRate::query()->create([
            'date' => $today,
            'usd_uzs' => $rate,
            'source' => CurrencyRate::SOURCE_CBU,
        ]);
    }
}
