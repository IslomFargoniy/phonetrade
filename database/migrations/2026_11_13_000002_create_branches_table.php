<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'user_id']);
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // null — egasining admin kassasi
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'type']);
        });

        Schema::create('installment_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('months');
            $table->decimal('markup_pct', 5, 2);
            $table->timestamps();

            $table->unique(['branch_id', 'months']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_rates');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }
};
