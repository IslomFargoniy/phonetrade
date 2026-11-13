<?php

namespace App\Services\Sales;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Do'kon ichida savdo raqami: 1, 2, 3 ... Takrorlanmasligi uchun hisoblagich qulflanadi.
 */
class SaleNumber
{
    public function next(): int
    {
        return DB::transaction(function (): int {
            Sequence::query()->firstOrCreate(['key' => 'sale']);

            $sequence = Sequence::query()->where('key', 'sale')->lockForUpdate()->firstOrFail();
            $sequence->update(['value' => $sequence->value + 1]);

            return $sequence->value;
        });
    }
}
