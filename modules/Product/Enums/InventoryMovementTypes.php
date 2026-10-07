<?php

namespace Modules\Product\Enums;

enum InventoryMovementTypes: int
{
    // 0 = initial, 1 = restock, 2 = sale, 3 = return, 4 = damage, 5= adjustment
    
    // Add Operations (Positive Quantity)
    case INITIAL = 0;
    case RESTOCK = 1;
    case RETURN = 3;

    // Remove Operations (Negative Quantity)
    case SALE = 2;
    case DAMAGE = 4;
    case ADJUSTMENT = 5;

    public function label(): string
    {
        return match($this) {
            self::INITIAL => 'Initial',
            self::RESTOCK => 'Restock',
            self::RETURN => 'Customer Return',
            self::SALE => 'Sale',
            self::DAMAGE => 'Damage/Lost',
            self::ADJUSTMENT => 'Stock Adjustment'
        };
    }

    public function operation(): string
    {
        return match($this) {
            self::INITIAL, self::RESTOCK, self::RETURN => 'add',
            self::SALE, self::DAMAGE, self::ADJUSTMENT => 'remove',
        };
    }

    public static function addOperations(): array
    {
        return [
            self::INITIAL->value => self::INITIAL->label(),
            self::RESTOCK->value => self::RESTOCK->label(),
            self::RETURN->value => self::RETURN->label(),
        ];
    }

    public static function removeOperations(): array
    {
        return [
            self::SALE->value => self::SALE->label(),
            self::DAMAGE->value => self::DAMAGE->label(),
            self::ADJUSTMENT->value => self::ADJUSTMENT->label(),
        ];
    }

    public static function labels():array
    {
        $labels = [];

        foreach(self::cases() as $movement_type) {
            $labels[$movement_type->value] = [
                'label' => $movement_type->label(),
                'operation' => $movement_type->operation(),
            ];
        }

        return $labels;
    }

    // For backward compatibility if needed
    public static function simpleLabels(): array
    {
        $labels = [];

        foreach(self::cases() as $movement_type) {
            $labels[$movement_type->value] = $movement_type->label();
        }

        return $labels;
    }
}