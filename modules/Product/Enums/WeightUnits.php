<?php

namespace Modules\Product\Enums;

enum WeightUnits: string
{
    case KG = 'kg';
    case G = 'g';
    case LBS = 'lbs';
    case PCS = 'pcs';
    case OZ = 'oz';
    
    public function label(): string
    {
        return match($this) {
            self::KG => 'Kilograms',
            self::G => 'Grams',
            self::LBS => 'Pounds',
            self::OZ => 'Ounces',
            self::PCS => 'Pieces',
        };
    }
}