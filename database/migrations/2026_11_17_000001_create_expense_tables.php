<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('rate', 12, 2)->nullable();
            $table->date('date');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        // Kassa bo'shatish: filial kassasidagi butun qoldiq admin kassaga o'tadi.
        Schema::create('cash_sweeps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('installment_payments', function (Blueprint $table) {
            // To'lov kunidagi kurs: hisobotlarda dollarli to'lov so'mga shu kurs bilan o'tkaziladi
            $table->decimal('rate', 12, 2)->nullable()->after('profit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->dropColumn('rate');
        });

        Schema::dropIfExists('cash_sweeps');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
