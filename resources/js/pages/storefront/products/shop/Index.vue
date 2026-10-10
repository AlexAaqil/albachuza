<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch, computed } from 'vue';
import Pagination from '@/components/custom/Pagination.vue';
import Input from '@/components/ui/input/Input.vue';
import shopPageRoutes from '@/routes/shop-page';
import type { Product } from '@/types/product';
import ProductCardSingleImage from '../../components/ProductCardSingleImage.vue';
import Categories from './Categories.vue';

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    products: {
        data: Product[];
        links: any[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            links: any[];
        };
    };
    product_categories: Category[];
    filters: {
        search?: string;
        category?: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');

const debouncedSearch = useDebounceFn(() => {
    router.get(shopPageRoutes.index().url, {
        search: search.value,
        category: selectedCategory.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

const selectCategory = (categorySlug: string) => {
    selectedCategory.value = categorySlug;
    // Reset to page 1 when changing category
    router.get(shopPageRoutes.index().url, {
        search: search.value,
        category: categorySlug || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    router.get(shopPageRoutes.index().url, {}, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    debouncedSearch();
});

watch(() => props.filters.category, (newCategory) => {
    if (selectedCategory.value !== newCategory) {
        selectedCategory.value = newCategory || '';
    }
});

const getDisplayRange = computed(() => {
    const { current_page, per_page, total } = props.products.meta;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);

    return { start, end, total };
});

const hasActiveFilters = computed(() => {
    return !!search.value || !!selectedCategory.value;
});
</script>

<template>
    <div class="container-fluid">
        <div class="search lg:w-[30dvw] lg:mx-auto py-2">
            <Input
                v-model="search"
                type="text"
                placeholder="Search products by name..."
                class="rounded-full px-6 py-6"
            />
        </div>

        <div v-if="hasActiveFilters" class="container-fluid mb-4">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm text-gray-600">Active filters:</span>
                <span v-if="selectedCategory" class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                    Category: {{ product_categories.find(c => c.slug === selectedCategory)?.name }}
                    <button @click="selectCategory(selectedCategory)" class="ml-1 hover:text-blue-600">×</button>
                </span>
                <button 
                    v-if="hasActiveFilters" 
                    @click="clearFilters" 
                    class="text-sm text-gray-500 hover:text-gray-700 underline"
                >
                    Clear all filters
                </button>
            </div>
        </div>

        <Categories :categories="product_categories" :selected-category="selectedCategory" @select-category="selectCategory"  />

        <div class="products-wrapper pb-16">
            <!-- Results count -->
            <div v-if="products.data.length > 0" class="text-sm text-gray-500 mb-4">
                Showing {{ getDisplayRange.start }} to {{ getDisplayRange.end }} 
                of {{ getDisplayRange.total }} shoes
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                <ProductCardSingleImage
                    v-for="product in products.data" 
                    :key="product.id" 
                    :product="product"
                />
            </div>

            <!-- Empty State -->
            <div v-if="products.data.length === 0" class="text-center py-16">
                <div class="text-gray-400 text-6xl mb-4">📦</div>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No products found</h3>
                <p class="text-gray-400">Try adjusting your search or filters. Or check back later!</p>
            </div>

            <!-- Pagination -->
            <Pagination :meta="products.meta" />
        </div>
    </div>
</template>
