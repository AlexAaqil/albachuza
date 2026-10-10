<?php

namespace Modules\Product\Concerns;

use Illuminate\Support\Str;

trait HasSku
{
    /**
     * Boot the trait: auto-generate a SKU before creating the model.
     */
    public static function bootHasSku(): void
    {
        static::creating(function ($model) {
            if (empty($model->sku)) {
                $model->sku = $model->generateSku();
            }
        });
    }

    /**
     * Generate a unique SKU in the format: [2 letters][1 digit]
     * Example: ZX4, KR7, BN2
     */
    public function generateSku(): string
    {
        $letters = $this->skuLetters();
        $digits  = $this->skuDigits();

        // Try up to 50 times to find a unique SKU
        for ($i = 0; $i < 50; $i++) {
            $candidate = Str::upper(
                $letters[array_rand($letters)] .
                $letters[array_rand($letters)] .
                $digits[array_rand($digits)]
            );

            if (! $this->skuExists($candidate)) {
                return $candidate;
            }
        }

        // Fallback: append extra digit(s) if namespace exhausted
        return $this->generateSkuWithSuffix();
    }

    /**
     * Letters allowed in SKUs. Consonants only to avoid
     * offensive words and sound-alike confusion.
     */
    protected function skuLetters(): array
    {
        return str_split('BCDFGHJKLMNPRSTVWXZ'); // 20 consonants
    }

    /**
     * Digits allowed in SKUs.
     */
    protected function skuDigits(): array
    {
        return str_split('0123456789');
    }

    /**
     * Check if a SKU already exists in the table.
     */
    protected function skuExists(string $sku): bool
    {
        return static::query()
            ->where('sku', $sku)
            ->exists();
    }

    /**
     * Fallback if 2-letter + 1-digit space is exhausted:
     * append an extra digit: ZX45, ZX46, etc.
     */
    protected function generateSkuWithSuffix(): string
    {
        $letters = $this->skuLetters();
        $base = Str::upper(
            $letters[array_rand($letters)] .
            $letters[array_rand($letters)]
        );

        for ($n = 0; $n < 1000; $n++) {
            $candidate = $base . $n;
            if (! $this->skuExists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a unique SKU.');
    }

    /**
     * Find a model by its SKU (case-insensitive).
     */
    public static function findBySku(string $sku): ?static
    {
        return static::query()
            ->whereRaw('UPPER(sku) = ?', [Str::upper($sku)])
            ->first();
    }

    /**
     * Route model binding via SKU.
     */
    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    /**
     * Validate a SKU string format.
     */
    public static function isValidSku(string $sku): bool
    {
        return (bool) preg_match('/^[BCDFGHJKLMNPRSTVWXZ]{2}[0-9]$/i', $sku);
    }
}