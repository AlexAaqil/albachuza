<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\ProductBrand;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductBrandSeeder extends Seeder
{
    private array $brands = [
        [
            'name' => 'Nike',
            'sort_order' => 1,
        ],
        [
            'name' => 'Laggz',
            'sort_order' => 2,
        ],
        [
            'name' => 'Addidas',
            'sort_order' => 3,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting brands import...');
        
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->brands as $brandData) {
            try {
                // Check if brand exists
                $existing = ProductBrand::where('name', $brandData['name'])->first();
                
                if ($existing) {
                    // Update if needed
                    $existing->update([
                        'sort_order' => $brandData['sort_order'] ?? $existing->sort_order,
                        'is_active' => true,
                    ]);
                    $updated++;
                    $this->command->line("Updated: {$brandData['name']}");
                } else {
                    // Create new brand
                    ProductBrand::create([
                        'name' => $brandData['name'],
                        'sort_order' => $brandData['sort_order'] ?? 0,
                        'is_active' => true,
                    ]);
                    $created++;
                    $this->command->line("✅ Created: {$brandData['name']}");
                }
                
            } catch (Exception $e) {
                $skipped++;
                $this->command->error("❌ Error: {$brandData['name']} - " . $e->getMessage());

                Log::error("Brand seeder error: " . $e->getMessage(), [
                    'brand' => $brandData['name'],
                ]);
            }
        }

        $this->command->newLine();
        $this->command->info("Results:");
        $this->command->info("✅ Created: $created");
        $this->command->info("⚒️ Updated: $updated");
        $this->command->info("⚠️ Skipped/Errors: $skipped");
        $this->command->newLine();
        $this->command->info('✅ Brand import complete!');
    }

    /**
     * Get all brand names (useful for validation)
     */
    public static function getbrandNames(): array
    {
        return array_column((new self())->brands, 'name');
    }
}
