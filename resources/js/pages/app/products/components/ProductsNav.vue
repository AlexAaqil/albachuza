<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import productBrandRoutes from '@/routes/product-brands';
import productCategoryRoutes from '@/routes/product-categories';
import productRoutes from '@/routes/products';
import productInventoryRoutes from '@/routes/products-inventory';

interface Props {
    currentPage: 'products' | 'discounts' | 'products-inventory' | 'product-categories' | 'product-brands';
}

defineProps<Props>();

const links = [
    { name: 'Products', href: productRoutes.index().url, key: 'products' },
    { name: 'Brands', href: productBrandRoutes.index(), key: 'product-brands' },
    { name: 'Categories', href: productCategoryRoutes.index(), key: 'product-categories' },
    { name: 'Inventory', href: productInventoryRoutes.index(), key: 'products-inventory' },
];
</script>

<template>
    <div class="products-nav pb-4 w-full border-b border-sidebar-border/80" aria-label="Breadcrumb">
        <ol class="flex items-center gap-2 text-sm">
            <li v-for="(item, idx) in links" :key="item.key" class="flex items-center gap-2">
                <Link
                    :href="item.href"
                    class="transition-colors"
                    :class="{
                        'text-foreground font-semibold pointer-events-none': currentPage === item.key,
                        'text-muted-foreground hover:text-foreground' : currentPage !== item.key
                    }"
                >
                    {{ item.name }}
                </Link>
                <span v-if="idx < links.length - 1" class="text-muted-foreground">/</span>
            </li>
        </ol>
    </div>
</template>
