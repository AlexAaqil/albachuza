<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Modules\User\Models\User;
use Modules\Payment\Models\Payment;
use Modules\Support\Concerns\HasUuid;
use Modules\Support\Concerns\HasCreatorAuditTrail;
use Modules\Order\Enums\OrderStatusEnum;
use Modules\Order\Enums\DeliveryStatusEnum;
use Modules\User\Enums\UserRoles;

class Order extends Model
{
    use HasUuid, HasCreatorAuditTrail;

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_selling_price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'sold_at' => 'datetime',
        'order_status' => OrderStatusEnum::class,
        'delivery_status' => DeliveryStatusEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderStatuses(): HasMany
    {
        return $this->hasMany(OrderStatus::class, 'order_id');
    }

    public function updateOrderStatus(OrderStatusEnum $status, ?string $notes = null, ?array $metadata = null, ?int $userId = null): OrderStatus
    {
        // Validate transition
        if (!$this->canTransitionToOrderStatus($status)) {
            throw new InvalidArgumentException("Invalid order status transition from {$this->order_status->value} to {$status->value}");
        }

        $statusRecord = $this->orderStatuses()->create([
            'type' => 'order',
            'status' => $status->value,
            'notes' => $notes,
            'metadata' => $metadata,
            'user_id' => $userId ?? Auth::id(),
            'is_system' => is_null($userId),
            'changed_at' => now(),
        ]);

        $this->update([
            'order_status' => $status->value,
        ]);

        return $statusRecord;
    }

    public function updateDeliveryStatus(DeliveryStatusEnum $status, ?string $notes = null, ?array $metadata = null, ?int $userId = null): OrderStatus
    {
        // Validate transition
        if (!$this->canTransitionToDeliveryStatus($status)) {
            throw new InvalidArgumentException("Invalid delivery status transition from {$this->delivery_status->value} to {$status->value}");
        }

        $statusRecord = $this->orderStatuses()->create([
            'type' => 'delivery',
            'status' => $status->value,
            'notes' => $notes,
            'metadata' => $metadata,
            'user_id' => $userId ?? Auth::id(),
            'is_system' => is_null($userId),
            'changed_at' => now(),
        ]);

        $this->update([
            'delivery_status' => $status->value,
        ]);

        return $statusRecord;
    }

    public function canTransitionToOrderStatus(OrderStatusEnum $newStatus): bool
    {
        $currentStatus = $this->order_status ?? OrderStatusEnum::PENDING;
        $validTransitions = $this->getValidOrderTransitions();

        // Safety check
        if (!isset($validTransitions[$currentStatus->value])) {
            return false;
        }
        
        return in_array($newStatus->value, $validTransitions[$currentStatus->value] ?? []);
    }

    public function canTransitionToDeliveryStatus(DeliveryStatusEnum $newStatus): bool
    {
        $currentStatus = $this->delivery_status ?? DeliveryStatusEnum::PENDING;
        $validTransitions = $this->getValidDeliveryTransitions();

        // Add safety check
        if (!isset($validTransitions[$currentStatus->value])) {
            return false;
        }
        
        return in_array($newStatus->value, $validTransitions[$currentStatus->value] ?? []);
    }

    protected function getValidOrderTransitions(): array
    {
        return [
            // From PENDING, you can:
            'pending' => [
                'confirmed',      // Order is confirmed
                'cancelled',      // Customer or admin cancels
                'processing',     // Skip confirmation and start processing
            ],
            
            // From CONFIRMED, you can:
            'confirmed' => [
                'processing',     // Start processing
                'cancelled',      // Cancel before processing
                'pending',        // Go back to pending (if needed)
            ],
            
            // From PROCESSING, you can:
            'processing' => [
                'ready_for_pickup', // Ready for customer pickup (shop)
                'completed',        // Mark as completed (if no delivery tracking)
                'cancelled',        // Cancel during processing
                'confirmed',        // Go back to confirmed (if needed)
                'pending',          // Go back to pending (if needed)
            ],
            
            // From READY_FOR_PICKUP, you can:
            'ready_for_pickup' => [
                'completed',        // Customer picked up
                'cancelled',        // Customer never picked up
                'processing',       // Go back to processing (if needed)
            ],
            
            // From COMPLETED, you can:
            'completed' => [
                'refunded',         // Post-sale refund
                'cancelled',        // Mark as cancelled (if needed)
                'returned',         // Customer returned item
            ],
            
            // From CANCELLED, you can:
            'cancelled' => [
                'refunded',         // Process refund
                'processing',       // Un-cancel (if customer changes mind)
                'completed',        // Complete without refund
            ],
            
            // From REFUNDED, you can:
            'refunded' => [
                'completed',        // Customer keeps item but got refund
            ],
            
            // From RETURNED, you can:
            'returned' => [
                'refunded',         // Process refund for returned items
                'completed',        // Return processed but no refund
                'processing',
            ],
        ];
    }

    protected function getValidDeliveryTransitions(): array
    {
        return [
            // From PENDING, you can:
            'pending' => [
                'picked_up',        // Courier picked up
                'delivered',        // Direct delivery (no tracking needed)
                'delivery_failed',  // Delivery attempt failed
                'returned',         // Returned before delivery
            ],
            
            // From PICKED_UP, you can:
            'picked_up' => [
                'in_transit',       // On the way
                'delivered',        // Already delivered (skip tracking)
                'delivery_failed',  // Something went wrong
                'returned',         // Returned to sender
            ],
            
            // From IN_TRANSIT, you can:
            'in_transit' => [
                'out_for_delivery', // Out for final delivery
                'delivered',        // Already delivered (skip out_for_delivery)
                'delivery_failed',  // Delivery failed
                'returned',         // Return to sender
            ],
            
            // From OUT_FOR_DELIVERY, you can:
            'out_for_delivery' => [
                'delivered',        // Successfully delivered
                'delivery_failed',  // Delivery attempt failed
                'returned',         // Return to sender
            ],
            
            // From DELIVERED, you can:
            'delivered' => [
                'returned',         // Customer returns item
                'delivery_failed',  // Mark as failed (if customer claims not received)
            ],
            
            // From DELIVERY_FAILED, you can:
            'delivery_failed' => [
                'returned',         // Return to sender
                'out_for_delivery', // Try delivery again
                'delivered',        // Actually was delivered
            ],
            
            // From RETURNED, you can:
            'returned' => [
                'pending',          // Re-send
                'delivered',        // Actually got delivered
                'delivery_failed',  // Failed again
            ],
        ];
    }

    public function isPending(): bool
    {
        return $this->order_status === OrderStatusEnum::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->order_status === OrderStatusEnum::CONFIRMED;
    }

    public function isProcessing(): bool
    {
        return $this->order_status === OrderStatusEnum::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->order_status === OrderStatusEnum::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->order_status === OrderStatusEnum::CANCELLED;
    }

    public function isRefunded(): bool
    {
        return $this->order_status === OrderStatusEnum::REFUNDED;
    }

    public function isReturned(): bool
    {
        return $this->order_status === OrderStatusEnum::RETURNED;
    }

    public function isDeliveryPending(): bool
    {
        return $this->delivery_status === DeliveryStatusEnum::PENDING;
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === DeliveryStatusEnum::DELIVERED;
    }

    public function isDeliveryFailed(): bool
    {
        return $this->delivery_status === DeliveryStatusEnum::DELIVERY_FAILED;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaymentStatusAttribute(): string
    {
        $total_paid = $this->payments()->sum('amount');

        if ($total_paid <= 0) {
            return 'pending';
        }

        if ($total_paid >= $this->total_selling_price) {
            return 'paid';
        }

        return 'partially_paid';
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, $this->total_selling_price - $this->total_paid);
    }

    public function isFullyPaid(): bool
    {
        return $this->total_paid >= $this->total_selling_price;
    }

    public function updateAmountPaid(): void
    {
        $this->amount_paid = $this->total_paid;
        $this->save();
    }

    public function getFullNameAttribute(): string
    {
        return $this->customer_name ?? 'Walk-in';
    }

    public function isShopPickup(): bool
    {
        return $this->delivery_method === 'shop';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->order_status->value, ['pending', 'confirmed', 'processing']);
    }

    public function canBeShipped(): bool
    {
        return $this->order_status === OrderStatusEnum::PROCESSING;
    }

    public function scopeCompleted($query)
    {
        return $query->where(function ($q) {
            $q->where('order_status', OrderStatusEnum::COMPLETED->value)
            ->orWhere('delivery_status', DeliveryStatusEnum::PICKED_UP->value);
        });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDelivered($query)
    {
        return $query->where('delivery_status', DeliveryStatusEnum::DELIVERED->value);
    }

    public function scopePaid($query)
    {
        return $query->whereColumn('amount_paid', '>=', 'total_selling_price')->where('total_selling_price', '>', 0);
    }

    public function scopePending($query)
    {
        return $query->where('order_status', OrderStatusEnum::PENDING->value);
    }

    public function scopeProcessing($query)
    {
        return $query->where('order_status', OrderStatusEnum::PROCESSING->value);
    }

    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            // CASE 1: Active orders that are NOT safely with courier
            $q->where(function ($sub) {
                $sub->whereNotIn('order_status', [
                    OrderStatusEnum::COMPLETED->value,
                    OrderStatusEnum::CANCELLED->value,
                    OrderStatusEnum::REFUNDED->value,
                ])
                ->whereNotIn('delivery_status', [
                    DeliveryStatusEnum::PICKED_UP->value,
                    DeliveryStatusEnum::IN_TRANSIT->value,
                    DeliveryStatusEnum::OUT_FOR_DELIVERY->value,
                ]);
            });
            
            // CASE 2: Payment issues (partial or unpaid)
            $q->orWhere(function ($sub) {
                $sub->whereColumn('amount_paid', '<', 'total_selling_price');
            });
            
            // CASE 3: Delivery failures (stolen, lost, etc.)
            $q->orWhere('delivery_status', DeliveryStatusEnum::DELIVERY_FAILED->value);
            
            // CASE 4: Returns (need processing)
            $q->orWhere('order_status', OrderStatusEnum::RETURNED->value);
            
            // CASE 5: Cancelled but not refunded
            $q->orWhere(function ($sub) {
                $sub->where('order_status', OrderStatusEnum::CANCELLED->value)
                    ->where('amount_paid', '>', 0);
            });
        });
    }

    public function scopePartiallyPaid($query)
    {
        return $query->whereColumn('amount_paid', '<', 'total_selling_price')
            ->where('amount_paid', '>', 0);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        $term = '%' . strtolower(trim($search)) . '%';

        return $query->where(function ($q) use ($term) {
            // Direct columns
            $q->whereRaw('LOWER(order_number) LIKE ?', [$term])
            ->orWhereRaw('LOWER(order_channel) LIKE ?', [$term])
            ->orWhereRaw('LOWER(customer_name) LIKE ?', [$term])
            ->orWhereRaw('LOWER(customer_phone) LIKE ?', [$term])
            ->orWhereRaw('LOWER(order_status) LIKE ?', [$term])

            // Cashier (created_by) name / email
            ->orWhereHas('createdBy', function ($sub) use ($term) {
                $sub->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            })

            // Updater (updated_by) name / email
            ->orWhereHas('updatedBy', function ($sub) use ($term) {
                $sub->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            })

            // Customer user (user_id) — keep if you still want it
            ->orWhereHas('user', function ($sub) use ($term) {
                $sub->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (in_array($user->role, [UserRoles::SUPER_ADMIN, UserRoles::ADMIN], true)) {
            return $query;
        }

        // Everyone else (cashiers) sees only what they created
        return $query->where('created_by', $user->id);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('created_at');
    }
}