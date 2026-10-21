# DB Design

## Migrations

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('phone', 20)->nullable();
    $table->unsignedTinyInteger('role')->default(3);
    $table->unsignedTinyInteger('status')->default(1);
    $table->string('image')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->boolean('is_anonymized')->default(false);
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();
});

Schema::create('password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});

Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});

Schema::create('product_categories', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name', 100)->unique();
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('image')->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->foreignId('parent_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
    $table->timestamps();

    $table->index('slug');
    $table->index('parent_id');
    $table->index(['is_active', 'sort_order']);
});

Schema::create('product_brands', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name', 100)->unique();
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('image')->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index('slug');
    $table->index(['is_active', 'sort_order']);
});

Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name', 200);
    $table->string('slug')->unique();
    $table->string('sku')->nullable()->unique();
    $table->string('barcode')->nullable();

    $table->string('size')->nullable();
    $table->text('description')->nullable();

    $table->string('type')->default('goods');

    $table->decimal('cost_price', 10, 2);
    $table->decimal('price', 10, 2);
    $table->boolean('is_new_arrival')->default(false);
    $table->boolean('is_featured')->default(false);
    $table->boolean('is_active')->default(true);
    $table->string('shoe_type')->default('mtumba'); // Mtumba or new

    // Inventory Tracking
    $table->decimal('current_stock', 10, 2)->default(0.00); // For services, default is 0
    $table->boolean('track_inventory')->default(true);
    $table->decimal('low_stock_threshold', 10, 2)->default(0.00);

    $table->string('unit_of_measure')->default('pcs');

    $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
    $table->foreignId('product_brand_id')->nullable()->constrained('product_brands')->nullOnDelete();

    $table->timestamp('date_of_purchase')->nullable();
    $table->timestamps();

    $table->index(['type', 'is_active']);
    $table->index('sku');
    $table->index('name');
    $table->index('barcode');
});

Schema::create('product_images', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->timestamps();

    $table->index(['product_id', 'sort_order']);
});

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

    $table->timestamps();

    $table->index(['product_id', 'created_at']);
    $table->index(['reference_type', 'reference_id']);
    $table->index(['created_at']);
});

Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('order_number')->unique();
    $table->string('order_channel')->default('pos');
    $table->string('order_status')->default('pending')->comment('pending, confirmed, processing, ready_for_pickup, completed, cancelled, refunded'); // current status for quick access

    // Fiscals
    $table->string('currency', 3)->default('KES');
    $table->decimal('subtotal', 10, 2)->default(0);
    $table->decimal('shipping_cost', 10, 2)->default(0);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('tax', 10, 2)->default(0);
    $table->decimal('total_cost_price', 10, 2)->default(0);
    $table->decimal('total_selling_price', 10, 2)->default(0); // subtotal + delivery
    $table->decimal('amount_paid', 10, 2)->default(0);

    $table->string('customer_name')->nullable();
    $table->string('customer_phone')->nullable();
    $table->string('customer_email')->nullable();

    $table->string('delivery_method')->default('shop');
    $table->string('delivery_location')->nullable();
    $table->string('delivery_area')->nullable();
    $table->string('delivery_address')->nullable();
    $table->string('tracking_number')->nullable();
    $table->string('delivery_status')->nullable()->comment('pending, picked_up, in_transit, out_for_delivery, delivered, delivery_failed, returned');

    $table->text('additional_info')->nullable();
    $table->text('admin_notes')->nullable();
    $table->text('cashier_notes')->nullable();

    // Snapshots for when user gets anonymized incase of account deletion
    // Customer information snapshot
    $table->string('customer_name_snapshot')->nullable();
    $table->string('customer_email_snapshot')->nullable();
    $table->string('customer_phone_snapshot')->nullable();
    
    // Delivery details snapshot
    $table->string('delivery_location_snapshot')->nullable();
    $table->string('delivery_area_snapshot')->nullable();
    $table->string('delivery_address_snapshot')->nullable();

    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('sold_at');
    $table->timestamps();
    
    // Indexes for fast lookups
    $table->index(['sold_at']);
    $table->index(['customer_phone']);
    $table->index(['created_by', 'created_at']);
    $table->index(['created_by', 'sold_at']);
});

Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    
    // Snapshot of product at time of purchase
    $table->string('product_name');
    $table->string('product_sku')->nullable();
    $table->string('product_type')->default('goods'); // [goods, service]

    // Used decimals in case of time-based items (e.g. 2.5 hours)
    $table->decimal('quantity', 10, 2)->default(1.00);
    $table->decimal('returned_quantity', 10, 2)->default(0.00); // in case a product gets returned

    $table->decimal('cost_price', 10, 2)->default(0);
    $table->decimal('selling_price', 10, 2)->default(0);
    $table->decimal('subtotal', 10, 2)->default(0);
    $table->decimal('tax', 10, 2)->default(0);
    $table->string('discount_type')->nullable(); // 'bulk', 'promo', 'clearance', 'manual'
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total', 10, 2)->default(0);

    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
    
    $table->timestamps();
    
    $table->index('order_id');
    $table->index('product_id');
});

Schema::create('order_status_logs', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('type')->default('order'); // order or delivery
    $table->string('status'); // pending, processing, etc.
    $table->string('notes')->nullable(); // admin notes about the specific transition
    $table->json('metadata')->nullable(); // For additional data like tracking info, location, etc.
    
    // Who changed the status?
    $table->boolean('is_system')->default(false); // true if system auto-changed
    $table->foreignId('user_id')->nullable()->constrained();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');

    $table->timestamp('changed_at'); // when it happened
    
    $table->timestamps();
    
    $table->index(['order_id', 'type']);
    $table->index(['order_id', 'changed_at']);
    $table->index('status');
});

Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('payment_method'); // [stripe, paypal, mpesa, card, bank_transfer]
    $table->string('transaction_reference')->nullable();
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('KES');
    $table->string('payment_status', 30)->default('pending'); // pending, completed, failed, refunded

    // Metadata block to avoid massive dedicated columns for card and mpesa specific details
    $table->json('payment_metadata')->nullable();

    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    
    $table->index('order_id');
    $table->index('transaction_reference');
    $table->index('payment_status');
    $table->index(['order_id', 'payment_method', 'payment_status']);
});
```
