<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\SendTransfer;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $branch = $this->branch($request);

        $transfers = StockTransfer::query()
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'items.unit.product:id,name', 'items.variant.product:id,name'])
            ->where(fn ($query) => $query->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Inertia::render('transfers/index', [
            'branchId' => $branch->id,
            'transfers' => $transfers->map(fn (StockTransfer $transfer) => [
                'id' => $transfer->id,
                'from' => $transfer->fromBranch->name,
                'to' => $transfer->toBranch->name,
                'incoming' => $transfer->to_branch_id === $branch->id,
                'status' => $transfer->status->value,
                'status_label' => $transfer->status->label(),
                'created_at' => $transfer->created_at?->format('d.m.Y H:i'),
                'note' => $transfer->note,
                'items' => $transfer->items->map(fn (StockTransferItem $item) => [
                    'id' => $item->id,
                    'name' => $item->unit->product->name ?? $item->variant->product->name ?? null,
                    'code' => $item->unit->imei1 ?? $item->variant->barcode ?? null,
                    'qty' => $item->qty,
                ]),
            ]),
        ]);
    }

    public function store(Request $request, SendTransfer $sendTransfer): RedirectResponse
    {
        $tenantId = $this->user($request)->tenant_id;

        $data = $request->validate([
            'to_branch_id' => ['required', 'integer', Rule::exists(Branch::class, 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'unit_ids' => ['nullable', 'array', 'max:500'],
            'unit_ids.*' => ['integer', Rule::exists(StockUnit::class, 'id')->where('tenant_id', $tenantId)],
            'quantities' => ['nullable', 'array', 'max:100'],
            'quantities.*.variant_id' => ['required', 'integer', Rule::exists(ProductVariant::class, 'id')->where('tenant_id', $tenantId)],
            'quantities.*.qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['to_branch_id' => 'Qabul qiluvchi filial']);

        $sendTransfer->handle(
            $this->branch($request),
            Branch::query()->whereKey($request->integer('to_branch_id'))->firstOrFail(),
            $this->user($request),
            array_values(array_map('intval', $data['unit_ids'] ?? [])),
            array_map(
                fn (array $line) => ['variant_id' => (int) $line['variant_id'], 'qty' => (int) $line['qty']],
                array_values($data['quantities'] ?? []),
            ),
            $data['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tovar jo'natildi."]);

        return to_route('transfers.index');
    }

    public function receive(Request $request, StockTransfer $transfer, ReceiveTransfer $receiveTransfer): RedirectResponse
    {
        $user = $this->user($request);

        // Faqat qabul qiluvchi filialda ishlaydigan xodim tasdiqlaydi.
        abort_unless(
            $transfer->status === TransferStatus::InTransit && $user->canAccessBranch($transfer->toBranch),
            403,
        );

        $receiveTransfer->handle($transfer, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tovar qabul qilindi.']);

        return to_route('transfers.index');
    }
}
