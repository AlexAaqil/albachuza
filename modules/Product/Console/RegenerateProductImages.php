<?php

namespace Modules\Product\Console;

use Illuminate\Console\Command;
use Modules\Product\Models\ProductImage;
use Modules\Product\Services\ProductImageService;
use Throwable;

class RegenerateProductImages extends Command
{
    protected $signature = 'product-images:regenerate
                            {--force : Rebuild the card and thumb files even if they already exist}';

    protected $description = 'Create the card and thumb versions for product photos that only have a full-size file';

    public function handle(ProductImageService $images): int
    {
        $query = ProductImage::query()->orderBy('id');

        if (! $this->option('force')) {
            $query->where(fn ($q) => $q->whereNull('card_name')->orWhereNull('thumb_name'));
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to do: every product photo already has its card and thumb versions.');

            return self::SUCCESS;
        }

        $this->info("Processing {$total} photo(s)...");

        $done = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(50, function ($chunk) use ($images, &$done, &$skipped, &$failed, $bar) {
            foreach ($chunk as $image) {
                try {
                    $images->regenerate($image, (bool) $this->option('force')) ? $done++ : $skipped++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Image #{$image->id} ({$image->name}): {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Generated: {$done}, skipped (file missing): {$skipped}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}