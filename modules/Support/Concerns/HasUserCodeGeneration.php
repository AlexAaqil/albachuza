<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasUserCodeGeneration
{
    /**
     * Boot the trait
     */
    protected static function bootHasUserCodeGeneration(): void
    {
        static::creating(function (Model $model) {
            // Auto-generate code if not provided
            if (empty($model->{$model->getCodeColumn()})) {
                $model->{$model->getCodeColumn()} = $model->generateUniqueCode();
            }
        });
    }

    /**
     * Get the code column name
     */
    protected function getCodeColumn(): string
    {
        return property_exists($this, 'codeColumn') 
            ? $this->codeColumn 
            : 'code';
    }

    /**
     * Get the code prefix
     */
    protected function getCodePrefix(): string
    {
        return property_exists($this, 'codePrefix') 
            ? $this->codePrefix 
            : 'COD';
    }

    /**
     * Generate a unique code
     */
    public function generateUniqueCode(): string
    {
        $prefix = $this->getCodePrefix();
        $column = $this->getCodeColumn();
        
        // Get the last used code
        $lastRecord = static::where($column, 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
        
        if (!$lastRecord) {
            $number = 1;
        } else {
            // Extract the number from the last code
            $lastCode = $lastRecord->{$column};
            $number = (int) substr($lastCode, strlen($prefix)) + 1;
        }
        
        // Format: PREFIX + 6-digit zero-padded number
        return $prefix . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a specific type of code with custom formatting
     */
    public static function generateCustomCode(string $prefix, int $length = 6): string
    {
        $lastRecord = static::latest('id')->first();
        $nextNumber = $lastRecord ? ((int) substr($lastRecord->code, strlen($prefix))) + 1 : 1;
        
        return $prefix . str_pad((string) $nextNumber, $length, '0', STR_PAD_LEFT);
    }
}