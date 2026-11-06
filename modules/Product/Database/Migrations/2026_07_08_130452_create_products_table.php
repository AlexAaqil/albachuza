<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 200);
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();

            $table->text('description')->nullable();
            $table->string('measurement')->nullable();
            $table->string('unit_of_measure')->default('pcs');

            $table->string('type')->default('goods'); // goods, services

            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            // Inventory Tracking
            $table->unsignedInteger('current_stock')->default(0); // For services, default is 0
            $table->boolean('track_inventory')->default(true);
            $table->unsignedTinyInteger('low_stock_threshold')->default(0);

            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('product_brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('sku');
            $table->index('name');
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
