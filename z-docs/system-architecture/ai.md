# DB Design (revised)

Reference for the Laravel migrations. In the real project each table becomes its own
migration file, created in the order shown here.

> **Rule once a shop is live:** until Albachuza goes live we can edit these freely.
> After that, every change must be a *new* migration. Each shop hosts its own copy,
> and every copy has to upgrade the same way.

Target databases: Postgres (main), MySQL/MariaDB and SQLite (offline/fallback).
Nothing below uses a feature that exists in only one of them, except one optional
CHECK constraint that is guarded by driver (see `products`).

---

## Conventions

### 1. Money is stored as integers in cents
- Every money column is an integer: **KES 1,500.00 is stored as `150000`**.
- No floats anywhere. SQLite has no true decimal type (it stores decimals as
  floating point), so integers are the only option that is exact on all three databases.
- Cents, not whole shillings, because tax (16% VAT) and percentage fees produce
  fractions of a shilling.
- Convert only at the edges: input string to cents on the way in, cents to a
  formatted string on the way out. Never `parseFloat(x) * 100`.

```php
// "1,500.50" -> 150050, no floats. Validate input first: ^\d+(\.\d{1,2})?$
function toCents(string $input): int
{
    $input = str_replace(',', '', trim($input));
    [$whole, $frac] = array_pad(explode('.', $input, 2), 2, '0');
    return ((int) $whole * 100) + (int) str_pad(substr($frac, 0, 2), 2, '0');
}

// Tax: rate in basis points (16% = 1600), round half up, once per line.
// $tax = intdiv($subtotal * $rateBps + 5000, 10000);
```

```js
// Display only (float is fine for display, never for arithmetic)
const kes = (cents) =>
  new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(cents / 100)
```

### 2. Money formulas (one definition, used everywhere)
Unit prices are per item; `discount` and `tax` on a line are totals for that line.

```
order_items.subtotal    = quantity * selling_price
order_items.total       = subtotal - discount + tax

orders.subtotal         = SUM(order_items.subtotal)
orders.discount         = SUM(order_items.discount) + any order-wide discount
orders.tax              = SUM(order_items.tax)
orders.total            = subtotal - discount + tax + delivery_cost
orders.total_cost_price = SUM(order_items.cost_price * quantity)

balance due             = total - amount_paid
gross profit            = (subtotal - discount) - total_cost_price   (excludes tax and delivery)
net profit (period)     = gross profit - SUM(expenses.amount)
```

Payment state is **derived**, not stored: `amount_paid = 0` is unpaid,
`0 < amount_paid < total` is partial, `amount_paid >= total` is paid.
`amount_paid` is updated in only one place: the same transaction that inserts a payment.

Stock purchases are **not** expenses. A pair's cost lives in `products.cost_price` and
is counted when the pair sells. Putting it in `expenses` too would count it twice.

### 3. Quantities are integers
Stock and sale quantities are whole numbers.

### 4. Timestamps
Stored in UTC. The "business day" for reports is computed in `Africa/Nairobi`.

### 5. Soft deletes
Used on `users`, `products`, `expenses`. A soft delete stamps `deleted_at` and keeps the
row; Laravel hides it from normal queries. Details and gotchas: see chat notes.
Not used on orders (they get a `cancelled` status) or on ledger tables (never deleted).

### 6. Append-only tables
`inventory_movements` and `order_status_logs` are never updated. They have a
`created_at`/`changed_at` and no `updated_at`. Corrections are new rows.

### 7. Foreign keys on financial and stock records
`restrictOnDelete`, never cascade. History must not disappear because a parent was deleted.

### 8. Enums (PHP backed enums)
| Enum | Values |
|---|---|
| `UserRole` (int) | set in code, e.g. owner, cashier, customer (default 3) |
| `UserStatus` (int) | active = 1, others in code |
| `ProductType` | `goods`, `service` |
| `ProductCondition` | `new`, `mitumba` |
| `MovementType` (int) | 0 initial, 1 restock, 2 sale, 3 return, 4 damage, 5 adjustment |
| `OrderChannel` | `pos`, `tiktok_live`, `storefront`, `other` |
| `OrderStatus` | `pending`, `confirmed`, `processing`, `ready_for_pickup`, `completed`, `cancelled`, `refunded` |
| `DeliveryStatus` | `pending`, `picked_up`, `in_transit`, `out_for_delivery`, `delivered`, `delivery_failed`, `returned` |
| `DeliveryMethod` | `shop` (pickup), plus delivery options in code |
| `DiscountType` | `bulk`, `promo`, `clearance`, `negotiated`, `manual` |
| `PaymentMethod` | `cash`, `mpesa`, `card`, `bank_transfer` (add others as needed) |
| `PaymentStatus` | `pending`, `completed`, `failed`, `refunded` |

---

## Migrations

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ---------------------------------------------------------------- users
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name');
    $table->string('email')->nullable()->unique();      // staff may log in with phone instead
    $table->string('phone', 20)->nullable()->unique();  // store normalised digits: 2547XXXXXXXX
    $table->unsignedTinyInteger('role')->default(3);    // UserRole
    $table->unsignedTinyInteger('status')->default(1);  // UserStatus
    $table->string('image')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->boolean('is_anonymized')->default(false);
    $table->softDeletes();                              // nullable deleted_at
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

// ---------------------------------------------------------------- catalogue
Schema::create('product_categories', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name', 100)->unique();
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('image')->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
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

// One row per pair for one-off items (mitumba); stock of 1.
// For new shoes with several units of one size, current_stock can be higher.
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name', 200);
    $table->string('slug')->unique();            // soft-deleted rows keep their slug
    $table->string('sku')->nullable()->unique(); // sticker code, e.g. 0142. Never reuse codes.
    $table->string('barcode')->nullable();

    $table->string('size')->nullable();
    $table->text('description')->nullable();

    $table->string('type')->default('goods');             // ProductType
    $table->string('item_condition')->default('new');     // ProductCondition: new | mitumba
    $table->string('grade', 10)->nullable();              // e.g. A / B / C

    // Money in cents (KES x 100)
    $table->unsignedBigInteger('cost_price');             // required
    $table->unsignedBigInteger('price');                  // current asking price

    $table->boolean('is_new_arrival')->default(false);
    $table->boolean('is_featured')->default(false);
    $table->boolean('is_active')->default(true);

    // Inventory tracking
    $table->integer('current_stock')->default(0);         // services: 0
    $table->integer('reserved_stock')->default(0);        // held by pending orders
    $table->boolean('track_inventory')->default(true);
    $table->integer('low_stock_threshold')->default(0);

    $table->string('unit_of_measure')->default('pcs');

    $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
    $table->foreignId('product_brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
    $table->softDeletes();
    $table->timestamps();

    $table->index(['type', 'is_active']);
    $table->index('sku');
    $table->index('name');
    $table->index('barcode');
});

// Database-level guard against negative stock / over-reservation.
// SQLite cannot add constraints after creation, so it relies on the conditional
// UPDATE in code (see below). That is enough on SQLite because it has one writer.
if (in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'])) {
    DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_check
        CHECK (current_stock >= 0 AND reserved_stock >= 0 AND reserved_stock <= current_stock)');
}
// Reserve a pair atomically; zero rows affected means someone else got it first:
//   UPDATE products SET reserved_stock = reserved_stock + 1
//   WHERE id = ? AND current_stock - reserved_stock >= 1

Schema::create('product_images', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->timestamps();

    $table->index(['product_id', 'sort_order']);
});

// Append-only stock ledger. Model: const UPDATED_AT = null;
Schema::create('inventory_movements', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();

    // MovementType: 0 initial, 1 restock, 2 sale, 3 return, 4 damage, 5 adjustment
    $table->unsignedTinyInteger('type');

    $table->integer('quantity');         // positive = addition, negative = subtraction
    $table->integer('quantity_before');  // stock before this movement (read under row lock)
    $table->integer('quantity_after');
    $table->string('source')->default('manual'); // manual | order | system

    $table->string('reference_type')->nullable();
    $table->unsignedBigInteger('reference_id')->nullable();

    $table->text('notes')->nullable();
    $table->json('metadata')->nullable();

    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

    $table->timestamp('created_at')->useCurrent();

    $table->index(['product_id', 'created_at']);
    $table->index(['reference_type', 'reference_id']);
    $table->index(['created_at']);
});

// ---------------------------------------------------------------- sales
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('order_number')->unique();          // generated in a service
    $table->string('order_channel')->default('pos');   // OrderChannel
    $table->string('order_status')->default('pending'); // OrderStatus (current status, for quick access)

    // Money in cents. Formulas: see "Money formulas".
    $table->string('currency', 3)->default('KES');
    $table->unsignedBigInteger('subtotal')->default(0);
    $table->unsignedBigInteger('delivery_cost')->default(0);
    $table->unsignedBigInteger('discount')->default(0);
    $table->unsignedBigInteger('tax')->default(0);
    $table->unsignedBigInteger('total')->default(0);             // subtotal - discount + tax + delivery_cost
    $table->unsignedBigInteger('total_cost_price')->default(0);
    $table->unsignedBigInteger('amount_paid')->default(0);

    $table->string('customer_name')->nullable();
    $table->string('customer_phone')->nullable();
    $table->string('customer_email')->nullable();

    $table->string('delivery_method')->default('shop');
    $table->string('delivery_location')->nullable();
    $table->string('delivery_area')->nullable();
    $table->string('delivery_address')->nullable();
    $table->string('tracking_number')->nullable();
    $table->string('delivery_status')->nullable(); // DeliveryStatus

    $table->text('additional_info')->nullable();
    $table->text('admin_notes')->nullable();
    $table->text('cashier_notes')->nullable();

    // Snapshots: kept permanently, so the order still shows who bought and where it
    // went after a user account is anonymized.
    $table->string('customer_name_snapshot')->nullable();
    $table->string('customer_email_snapshot')->nullable();
    $table->string('customer_phone_snapshot')->nullable();
    $table->string('delivery_location_snapshot')->nullable();
    $table->string('delivery_area_snapshot')->nullable();
    $table->string('delivery_address_snapshot')->nullable();

    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

    $table->timestamp('reserved_until')->nullable(); // pending live-sale reservation expiry
    $table->timestamp('sold_at')->nullable();        // set when the order completes
    $table->timestamps();

    $table->index(['sold_at']);
    $table->index(['customer_phone']);
    $table->index(['order_status']);
    $table->index(['reserved_until']);
    $table->index(['created_by', 'created_at']);
    $table->index(['created_by', 'sold_at']);
});

Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();

    // Snapshot of the product at time of sale
    $table->string('product_name');
    $table->string('product_sku')->nullable();
    $table->string('product_type')->default('goods'); // ProductType

    $table->integer('quantity')->default(1);
    $table->integer('returned_quantity')->default(0);

    // Money in cents. Unit prices; discount and tax are line totals.
    $table->unsignedBigInteger('cost_price')->default(0);     // per unit
    $table->unsignedBigInteger('selling_price')->default(0);  // per unit = asking price at time of sale
    $table->unsignedBigInteger('subtotal')->default(0);       // quantity * selling_price
    $table->unsignedBigInteger('tax')->default(0);
    $table->string('discount_type')->nullable();              // DiscountType; bargaining = 'negotiated'
    $table->unsignedBigInteger('discount')->default(0);
    $table->unsignedBigInteger('total')->default(0);          // subtotal - discount + tax

    $table->foreignId('order_id')->constrained()->restrictOnDelete();
    $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();

    $table->timestamps();

    $table->index('order_id');
    $table->index('product_id');
});

// Append-only status history. (Was order_statuses.)
Schema::create('order_status_logs', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('type')->default('order'); // order | delivery
    $table->string('status');
    $table->string('notes')->nullable();
    $table->json('metadata')->nullable();

    $table->boolean('is_system')->default(false);
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('order_id')->constrained()->restrictOnDelete();

    $table->timestamp('changed_at')->useCurrent();

    $table->index(['order_id', 'type']);
    $table->index(['order_id', 'changed_at']);
    $table->index('status');
});

Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('payment_method');                 // PaymentMethod
    $table->string('transaction_reference')->nullable(); // e.g. M-PESA code
    $table->bigInteger('amount');                     // cents; signed so a refund can be negative
    $table->string('currency', 3)->default('KES');
    $table->string('payment_status', 30)->default('pending'); // PaymentStatus

    // Provider references and callback payloads. Never store raw card details.
    $table->json('payment_metadata')->nullable();

    $table->foreignId('order_id')->constrained()->restrictOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    // The same M-PESA code cannot be recorded twice. NULL references (cash) are not
    // compared. A refund needs its own reference (the reversal code), not the original's.
    $table->unique(['payment_method', 'transaction_reference']);

    $table->index('order_id');
    $table->index('transaction_reference');
    $table->index('payment_status');
    $table->index(['order_id', 'payment_method', 'payment_status']);
});

// ---------------------------------------------------------------- expenses
Schema::create('expense_categories', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();
    $table->string('name', 100)->unique();   // e.g. Transport to market, Rent, Airtime/Data, Packaging
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Running costs of the business. NOT stock purchases (those are products.cost_price).
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->uuid()->unique();

    $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
    $table->string('description');
    $table->unsignedBigInteger('amount');            // cents
    $table->date('incurred_on');                     // when it was spent (may differ from entry date)

    $table->string('payment_method')->default('cash'); // PaymentMethod
    $table->string('transaction_reference')->nullable(); // M-PESA code etc.
    $table->string('paid_to')->nullable();           // payee: landlord, KPLC, rider...
    $table->string('receipt_image')->nullable();
    $table->text('notes')->nullable();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->softDeletes();
    $table->timestamps();

    $table->index('incurred_on');
    $table->index(['expense_category_id', 'incurred_on']);
    $table->index(['created_by', 'incurred_on']);
});

// ---------------------------------------------------------------- settings
// Per-shop configuration, since each shop runs its own copy.
// Example keys: shop_name, shop_phone, logo, till_number, receipt_footer, currency.
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

---

## Changes from the original

Fixes

- Removed `product_brands.parent_id` (copy-paste error pointing at categories).
- `payments.paid_at` and `orders.sold_at` are now nullable.
- Removed the "raw card details" comment on `payment_metadata`.
- Cascade deletes replaced with `restrictOnDelete` on stock and financial records.

Fit for Albachuza

- `is_new` renamed `is_new_arrival`; added `item_condition` (`new` | `mitumba`, not named
  `condition` because that is a reserved word in MySQL) and nullable `grade`.
- `track_inventory` defaults to true; `cost_price` is required.
- Added `reserved_stock` (products) and `reserved_until` (orders): a pending order is the
  reservation. Added a stock CHECK constraint on Postgres/MySQL.

Money and quantities

- All money columns are integers in cents. All quantities are integers.
- `total_selling_price` renamed `total`; `shipping_cost` renamed `delivery_cost`;
  snapshot columns renamed `delivery_*_snapshot`. Formulas defined above.
- Bargaining is recorded as a `discount` with `discount_type = 'negotiated'`.

Ledgers and payments

- `inventory_movements`: dropped `updated_by`/`updated_at`; `created_at` records when it happened.
- `order_statuses` renamed `order_status_logs`; `changed_at` replaces `timestamps()`.
- `payments`: added `created_by` and a unique index on `(payment_method, transaction_reference)`.

Users

- `email` nullable, `phone` unique, `softDeletes()` instead of a bare `deleted_at`.

Added

- `expense_categories`, `expenses`, `settings`, `uuid` on `product_images`.
- Index on `orders.order_status` and `orders.reserved_until`.
