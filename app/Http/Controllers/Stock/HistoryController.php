<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Models\SaleItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitta telefonning yo'li: kirim → ko'chirish → sotuv → qaytish. Kafolatni tekshirish ham shu yerda.
 */
class HistoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $this->user($request);
        $code = trim((string) $request->query('code', ''));

        $unit = $code !== ''
            ? StockUnit::query()
                ->with(['product:id,name,image_path', 'branch:id,name', 'photos', 'purchaseItem.purchase.supplier', 'purchaseItem.purchase.customer'])
                ->where(fn ($query) => $query->where('imei1', $code)->orWhere('imei2', $code)->orWhere('barcode', $code))
                ->first()
            : null;

        return Inertia::render('stock/history', [
            'code' => $code,
            'unit' => $unit ? StockPresenter::unit($unit, $user->isOwner()) : null,
            'events' => $unit ? $this->events($unit) : [],
        ]);
    }

    /**
     * @return list<array{date: string|null, title: string, detail: string|null, sale_id: int|null}>
     */
    private function events(StockUnit $unit): array
    {
        $events = [];
        $purchase = $unit->purchaseItem?->purchase;

        if ($purchase !== null) {
            $events[] = [
                'at' => $purchase->created_at,
                'title' => 'Kirim · '.$purchase->type->label(),
                'detail' => $purchase->supplier->name ?? $purchase->customer->full_name ?? null,
                'sale_id' => null,
            ];
        }

        $transfers = StockTransferItem::query()
            ->where('stock_unit_id', $unit->id)
            ->get()
            ->map(fn (StockTransferItem $item) => StockTransfer::query()->with(['fromBranch:id,name', 'toBranch:id,name'])->find($item->stock_transfer_id))
            ->filter();

        foreach ($transfers as $transfer) {
            $events[] = [
                'at' => $transfer->created_at,
                'title' => "Ko'chirish · ".$transfer->status->label(),
                'detail' => "{$transfer->fromBranch->name} → {$transfer->toBranch->name}",
                'sale_id' => null,
            ];
        }

        $items = SaleItem::query()->with(['sale.customer', 'sale.returns'])->where('stock_unit_id', $unit->id)->get();

        foreach ($items as $item) {
            $sale = $item->sale;

            $events[] = [
                'at' => $sale->created_at,
                'title' => "Sotuv · chek №{$sale->number}",
                'detail' => collect([
                    $sale->customer->full_name ?? null,
                    $item->warranty_until ? 'kafolat '.$item->warranty_until->format('d.m.Y').' gacha' : null,
                ])->filter()->join(' · ') ?: null,
                'sale_id' => $sale->id,
            ];

            foreach ($sale->returns as $return) {
                $events[] = [
                    'at' => $return->created_at,
                    'title' => 'Qaytarish',
                    'detail' => $return->reason,
                    'sale_id' => $sale->id,
                ];
            }
        }

        usort($events, fn (array $a, array $b) => ($a['at']?->getTimestamp() ?? 0) <=> ($b['at']?->getTimestamp() ?? 0));

        return array_map(fn (array $event) => [
            'date' => $event['at']?->format('d.m.Y H:i'),
            'title' => $event['title'],
            'detail' => $event['detail'],
            'sale_id' => $event['sale_id'],
        ], $events);
    }
}
