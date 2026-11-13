<?php

namespace Tests\Feature\Stock;

use App\Actions\Branches\CreateBranch;
use App\Actions\Stock\ReceiveStock;
use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\SendTransfer;
use App\Enums\StockStatus;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Services\Stock\BatchConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_is_in_transit_until_the_other_branch_accepts_it(): void
    {
        $store = StoreFixture::create();
        $other = app(CreateBranch::class)->handle(['name' => 'Yunusobod']);

        app(ReceiveStock::class)->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'USD', 'lines' => [[
            'product_id' => $store->phone->id, 'unit_cost' => '320', 'sale_price' => '4850000', 'sale_currency' => 'UZS',
            'units' => [['imei1' => StoreFixture::imei(1)]],
        ]]]);
        $unit = StockUnit::query()->firstOrFail();

        $transfer = app(SendTransfer::class)->handle($store->branch, $other, $store->owner, [$unit->id]);

        $this->assertSame(StockStatus::InTransit, $unit->fresh()->status);
        $this->assertSame($store->branch->id, $unit->fresh()->branch_id);

        app(ReceiveTransfer::class)->handle($transfer, $store->owner);

        $this->assertSame(StockStatus::InStock, $unit->fresh()->status);
        $this->assertSame($other->id, $unit->fresh()->branch_id);
        $this->assertSame(TransferStatus::Received, $transfer->fresh()->status);
    }

    public function test_accessories_move_oldest_batch_first_and_keep_their_cost(): void
    {
        $store = StoreFixture::create();
        $other = app(CreateBranch::class)->handle(['name' => 'Yunusobod']);
        $line = fn (string $cost) => [
            'product_id' => $store->case->id, 'barcode' => '4780012345678', 'unit_cost' => $cost,
            'sale_price' => '60000', 'sale_currency' => 'UZS', 'qty' => 10,
        ];
        app(ReceiveStock::class)->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [$line('20000')]]);
        app(ReceiveStock::class)->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [$line('25000')]]);
        $variant = ProductVariant::query()->firstOrFail();

        $transfer = app(SendTransfer::class)->handle($store->branch, $other, $store->owner, [], [['variant_id' => $variant->id, 'qty' => 12]]);

        $consumer = app(BatchConsumer::class);
        $this->assertSame(8, $consumer->available($variant, $store->branch));
        $this->assertSame(0, $consumer->available($variant, $other));

        app(ReceiveTransfer::class)->handle($transfer, $store->owner);

        $this->assertSame(12, $consumer->available($variant, $other));
        $this->assertSame(
            [['20000.00', 10], ['25000.00', 2]],
            StockBatch::query()->where('branch_id', $other->id)->orderBy('id')->get()
                ->map(fn (StockBatch $batch) => [$batch->unit_cost, $batch->qty_left])->all(),
        );
    }

    public function test_transfer_cannot_be_received_twice(): void
    {
        $store = StoreFixture::create();
        $other = app(CreateBranch::class)->handle(['name' => 'Yunusobod']);
        app(ReceiveStock::class)->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [[
            'product_id' => $store->case->id, 'unit_cost' => '20000', 'sale_price' => '60000', 'sale_currency' => 'UZS', 'qty' => 5,
        ]]]);
        $variant = ProductVariant::query()->firstOrFail();
        $transfer = app(SendTransfer::class)->handle($store->branch, $other, $store->owner, [], [['variant_id' => $variant->id, 'qty' => 5]]);

        app(ReceiveTransfer::class)->handle($transfer, $store->owner);

        $this->expectException(BusinessRuleException::class);

        app(ReceiveTransfer::class)->handle($transfer, $store->owner);
    }

    public function test_more_than_the_stock_cannot_be_sent(): void
    {
        $store = StoreFixture::create();
        $other = app(CreateBranch::class)->handle(['name' => 'Yunusobod']);
        app(ReceiveStock::class)->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [[
            'product_id' => $store->case->id, 'unit_cost' => '20000', 'sale_price' => '60000', 'sale_currency' => 'UZS', 'qty' => 5,
        ]]]);
        $variant = ProductVariant::query()->firstOrFail();

        try {
            app(SendTransfer::class)->handle($store->branch, $other, $store->owner, [], [['variant_id' => $variant->id, 'qty' => 6]]);
            $this->fail('Ko\'chirish rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('STOCK_INSUFFICIENT', $exception->errorCode);
        }

        $this->assertSame(5, app(BatchConsumer::class)->available($variant, $store->branch));
    }
}
