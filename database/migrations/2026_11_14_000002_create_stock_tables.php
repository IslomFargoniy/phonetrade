<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kassa jurnali: balans alohida saqlanmaydi, shu yozuvlardan hisoblanadi. Yozuvlar o'zgartirilmaydi.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 3);
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('kind', 40);
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['cash_account_id', 'currency']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // supplier — yetkazib beruvchidan, opening — boshlang'ich qoldiq, trade_in — mijozdan sotib olish
            $table->string('type', 20);
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('currency', 3);
            // Kirim kunidagi kurs: foyda shu kurs bilan hisoblanadi
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('total', 18, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('sale_price', 18, 2);
            $table->string('sale_currency', 3);
            $table->decimal('max_discount_pct', 5, 2)->default(0);
            $table->unsignedSmallInteger('warranty_months')->default(0);
            $table->json('attributes')->nullable();
        });

        Schema::create('stock_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('imei1', 15);
            $table->string('imei2', 15)->nullable();
            $table->string('barcode', 64);
            $table->json('attributes')->nullable();
            $table->decimal('cost', 18, 2);
            $table->string('cost_currency', 3);
            $table->decimal('cost_rate', 12, 2)->nullable();
            $table->decimal('sale_price', 18, 2);
            $table->string('sale_currency', 3);
            $table->decimal('max_discount_pct', 5, 2)->default(0);
            $table->unsignedSmallInteger('warranty_months')->default(0);
            $table->string('status', 20)->default('in_stock');
            $table->timestamps();

            $table->unique(['tenant_id', 'imei1']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'imei2']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        // Aksesuar partiyasi: tannarx FIFO tartibida shu partiyalardan olinadi.
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty_in');
            $table->unsignedInteger('qty_left');
            $table->decimal('unit_cost', 18, 2);
            $table->string('cost_currency', 3);
            $table->decimal('cost_rate', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'variant_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 20)->default('in_transit');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('qty')->default(1);
            // Aksesuar ko'chirilganda tannarx o'zi bilan ketadi
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->decimal('cost_rate', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['stock_transfer_items', 'stock_transfers', 'stock_batches', 'stock_units', 'purchase_items', 'purchases', 'cash_movements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
