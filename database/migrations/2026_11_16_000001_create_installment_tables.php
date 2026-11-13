<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Yopiq diskdagi yo'l: tashqaridan to'g'ridan-to'g'ri ochilmaydi
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('currency', 3);
            // A — boshlang'ichsiz (ustama butun narxga), B — boshlang'ich bilan (ustama qoldiqqa)
            $table->string('mode', 1);
            $table->decimal('price', 18, 2);
            $table->decimal('down_payment', 18, 2)->default(0);
            $table->decimal('markup_pct', 5, 2);
            $table->unsignedTinyInteger('months');
            $table->unsignedTinyInteger('pay_day');
            // Ustamali qarz: shartnoma tuzilganda bir marta hisoblanadi va keyin o'smaydi
            $table->decimal('total_debt', 18, 2);
            // Jami tushum = boshlang'ich + ustamali qarz
            $table->decimal('total_revenue', 18, 2);
            $table->decimal('total_profit', 18, 2);
            // Har to'lovning qancha qismi foyda ekani
            $table->decimal('profit_ratio', 12, 10);
            $table->decimal('paid_total', 18, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('installment_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('no');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);

            $table->index('due_date');
        });

        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Shartnoma valyutasida
            $table->decimal('amount', 18, 2);
            $table->decimal('profit_amount', 18, 2);
            // Savdo paytidagi to'lov (boshlang'ich yoki 1-oylik)
            $table->boolean('is_initial')->default(false);
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            // Nasiyali savdoda qaytariladigan summa shartnoma valyutasida bo'ladi
            $table->string('currency', 3)->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        foreach (['installment_payments', 'installment_schedule', 'installment_plans', 'customer_files'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
