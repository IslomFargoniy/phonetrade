<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Har bir telefonning o'z rasmlari (ishlatilgan telefonlar bir-biridan farq qiladi).
        Schema::create('stock_unit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_unit_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['stock_unit_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_unit_photos');
    }
};
