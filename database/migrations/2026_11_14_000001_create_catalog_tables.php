<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // serial — har dona alohida (telefon), quantity — miqdor bilan (aksesuar)
            $table->string('tracking', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // select — ro'yxatdan, number — son, text — matn
            $table->string('type', 20);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('attribute_category', function (Blueprint $table) {
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->primary(['attribute_id', 'category_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('default_warranty_months')->default(0);
            $table->decimal('default_max_discount_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // Miqdor bilan yuritiladigan tovar varianti: bitta barcode, narx va chegirma shu yerda.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 64);
            $table->json('attributes')->nullable();
            $table->decimal('sale_price', 18, 2)->default(0);
            $table->string('sale_currency', 3)->default('UZS');
            $table->decimal('max_discount_pct', 5, 2)->default(0);
            $table->unsignedSmallInteger('warranty_months')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'barcode']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 12)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 12);
            $table->string('phone2', 12)->nullable();
            $table->string('passport_series', 4)->nullable();
            $table->string('passport_number', 10)->nullable();
            $table->string('pinfl', 14)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'pinfl']);
        });

        // Do'kon ichidagi ketma-ket raqamlar (ichki barcode, hujjat raqami).
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->unsignedBigInteger('value')->default(0);

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['sequences', 'customers', 'suppliers', 'product_variants', 'products', 'attribute_category', 'attribute_options', 'attributes', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
