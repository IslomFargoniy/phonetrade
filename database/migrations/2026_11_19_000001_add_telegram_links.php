<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->index();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->index();
        });

        // Yuborilgan eslatmalar: bitta eslatma ikki marta ketmasligi uchun.
        Schema::create('telegram_notices', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_notices');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
