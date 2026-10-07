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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Movement Details
            // Enum: 0 = initial, 1 = restock, 2 = sale, 3 = return, 4 = damage, 5= adjustment
            $table->unsignedTinyInteger('type');

            $table->decimal('quantity', 15, 2); // positive = addition, negative = subtraction
            $table->decimal('quantity_before', 15, 2); // Stock level before this movment
            $table->decimal('quantity_after', 15, 2);
            $table->string('source')->default('manual'); // manual | order | system

            // Reference to other tables
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Additional info
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
