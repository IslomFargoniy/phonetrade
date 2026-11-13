<?php

namespace App\Services\Rates;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Markaziy bankning rasmiy dollar kursini oladi.
 */
class CbuRateFetcher
{
    private const URL = 'https://cbu.uz/uz/arkhiv-kursov-valyut/json/USD/';

    /**
     * 1 dollar necha so'm; olib bo'lmasa null.
     */
    public function fetch(): ?string
    {
        try {
            $rate = Http::timeout(10)->retry(2, 500)->get(self::URL)->throw()->json('0.Rate');
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return number_format((float) $rate, 2, '.', '');
    }
}
