<?php

namespace App\Services\Stock;

use App\Models\Sequence;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Ichki barcode beradi: do'kon prefiksi + tartib raqami (masalan, TD-000481).
 */
class BarcodeGenerator
{
    public const DEFAULT_PREFIX = 'TD';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Ketma-ket $count ta barcode. Hisoblagich qatori qulflanadi, shuning uchun takror chiqmaydi.
     *
     * @return list<string>
     */
    public function next(int $count = 1): array
    {
        if ($count < 1) {
            return [];
        }

        return DB::transaction(function () use ($count): array {
            Sequence::query()->firstOrCreate(['key' => 'barcode']);

            $sequence = Sequence::query()->where('key', 'barcode')->lockForUpdate()->firstOrFail();
            $start = $sequence->value + 1;
            $sequence->update(['value' => $sequence->value + $count]);

            $prefix = $this->prefix();

            return array_map(
                fn (int $number) => sprintf('%s-%06d', $prefix, $number),
                range($start, $start + $count - 1),
            );
        });
    }

    private function prefix(): string
    {
        $tenant = Tenant::query()->find($this->context->id());
        $prefix = $tenant?->setting('barcode_prefix');

        return is_string($prefix) && $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }
}
