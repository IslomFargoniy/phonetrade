<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tariflar platformaga tegishli (do'konga emas): superadmin boshqaradi.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Oylik narx, so'mda
            $table->decimal('price', 18, 2)->default(0);
            // null — cheklanmagan
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // null — muddat qo'yilmagan (cheklanmagan)
            $table->date('paid_until')->nullable();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name');
            $table->decimal('amount', 18, 2);
            $table->unsignedInteger('months');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Boshlang'ich tariflar. Narxni superadmin panelda belgilaydi.
        $now = now();
        DB::table('plans')->insert([
            ['name' => "Boshlang'ich", 'price' => 0, 'max_branches' => 1, 'max_users' => 3, 'sort' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Standart', 'price' => 0, 'max_branches' => 3, 'max_users' => 10, 'sort' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cheklanmagan', 'price' => 0, 'max_branches' => null, 'max_users' => null, 'sort' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('paid_until');
        });

        Schema::dropIfExists('plans');
    }
};
