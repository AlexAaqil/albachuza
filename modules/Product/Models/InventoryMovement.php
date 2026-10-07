<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Concerns\HasUuid;
use Modules\Product\Enums\InventoryMovementTypes;
use Modules\Support\Concerns\HasCreatorAuditTrail;
use Modules\User\Models\User;

class InventoryMovement extends Model
{
    use HasUuid, HasCreatorAuditTrail;

    protected $guarded = [];

    protected $casts = [
        'type' => InventoryMovementTypes::class,
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type?->label() ?? 'Unknown';
    }

    public function getIsSystemAttribute(): bool
    {
        return $this->source !== 'manual';
    }

    public function getPerformedByNameAttribute(): string
    {
        return $this->is_system ? 'System' : ($this->createdBy?->name ?? 'Unknown');
    }
}
