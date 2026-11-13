<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Egasi ruxsat bergan sotuvchi savdoni qaytara oladi
            $table->boolean('can_refund')->default(false)->after('is_active');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            // full — to'liq to'lov, installment — muddatli
            $table->string('type', 20);
            // Savat bir valyutada bo'lsa shu valyuta, aralash bo'lsa so'm
            $table->string('currency', 3);
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('change', 18, 2)->default(0);
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'created_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('returned_qty')->default(0);
            // Narx va chegirma tovarning o'z valyutasida
            $table->decimal('price', 18, 2);
            $table->string('currency', 3);
            $table->decimal('discount', 18, 2)->default(0);
            // Qator jami, tannarx va foyda — savdo valyutasida
            $table->decimal('final_price', 18, 2);
            $table->decimal('cost_snapshot', 18, 2);
            $table->decimal('profit', 18, 2);
            $table->date('warranty_until')->nullable();

            $table->index('stock_unit_id');
        });

        // Aksesuar qaysi partiyadan olingani: qaytarishda o'sha partiyaga qaytadi.
        Schema::create('sale_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('returned_qty')->default(0);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->string('method', 20);
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->decimal('rate', 12, 2)->nullable();
            // Savdo valyutasidagi ekvivalenti
            $table->decimal('amount_base', 18, 2);
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            // Savdo valyutasida
            $table->decimal('refund_amount', 18, 2);
            $table->decimal('profit_reversed', 18, 2);
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('amount', 18, 2);
            $table->decimal('profit', 18, 2);
        });
    }

    public function down(): void
    {
        foreach (['sale_return_items', 'sale_returns', 'sale_payments', 'sale_item_batches', 'sale_items', 'sales'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_refund');
        });
    }
};
