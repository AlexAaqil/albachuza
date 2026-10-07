<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2, Loader2, AlertCircle } from '@lucide/vue';
import { ref, computed } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import Pagination from '@/components/custom/Pagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import ProductsNav from '../components/ProductsNav.vue';
import productRoutes from '@/routes/products';
import type { Product } from '@/types/product';

const { formatPrice } = usePriceFormatter();

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
    filters: {
        search?: string;
        status?: string;
    };
    duplicate_product_ids?: number[];
}

const props = defineProps<Props>();

const search = ref(props.filters?.search || '');

const handleSearch = (value: string) => {
    router.get(productRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const toggling = ref<{ id: number; attribute: string } | null>(null);

// Create a set of duplicate IDs for faster lookup
const duplicateIdsSet = computed(() => {
    return new Set(props.duplicate_product_ids || []);
});

// Check if a product is a duplicate
const isDuplicate = (productId: number): boolean => {
    return duplicateIdsSet.value.has(productId);
};

// Count total duplicate products
const duplicateCount = computed(() => {
    return duplicateIdsSet.value.size;
});

// Get duplicate products count with duplicates count
const duplicateSummary = computed(() => {
    const duplicates = props.duplicate_product_ids || [];
    const uniqueDuplicates = new Set(duplicates);

    return {
        totalDuplicates: duplicates.length, // Total duplicate entries
        uniqueGroups: uniqueDuplicates.size, // Number of unique products with duplicates
    };
});

const getDisplayRange = computed(() => {
    const { current_page, per_page, total } = props.products.meta;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);

    return { start, end, total };
});

const hasActiveFilters = computed(() => !!search.value);

const toggleAttribute = async (product: Product, attribute: 'is_featured' | 'is_new' | 'is_active' | 'track_inventory') => {
    const currentValue = product[attribute];
    const newValue = !currentValue;
    
    toggling.value = { id: product.id, attribute };
    
    try {
        const response = await fetch(productRoutes.toggleAttribute(product.uuid).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                attribute: attribute,
                value: newValue
            })
        });

        if (!response.ok) {
            throw new Error('Failed to update');
        }

        const data = await response.json();
        
        if (data.success) {
            product[attribute] = newValue;
        }
    } catch (error) {
        console.error('Error toggling attribute:', error);
        product[attribute] = currentValue;
    } finally {
        toggling.value = null;
    }
};

const getTagClasses = (type: string, value: boolean) => {
    const baseClasses = 'px-1 py-0.5 text-xs font-medium rounded-sm transition-all duration-200 cursor-pointer select-none';
    
    if (!value) {
        return `${baseClasses} bg-gray-100 text-gray-400 hover:bg-gray-200`;
    }
    
    switch (type) {
        case 'is_featured':
            return `${baseClasses} bg-yellow-400 text-yellow-900 hover:bg-yellow-500`;
        case 'is_new':
            return `${baseClasses} bg-green-500 text-white hover:bg-green-600`;
        case 'is_active':
            return `${baseClasses} bg-blue-500 text-white hover:bg-blue-600`;
        case 'track_inventory':
            return `${baseClasses} bg-blue-500 text-white hover:bg-blue-600`;
        default:
            return baseClasses;
    }
};

const isLoading = (product: Product, attribute: string) => {
    return toggling.value?.id === product.id && toggling.value?.attribute === attribute;
};

const truncateDescription = (text: string, maxLength: number = 60): string => {
    if (!text) {
        return '-';
    }

    if (text.length <= maxLength) {
        return text;
    }

    return text.substring(0, maxLength) + '...';
};

// A product is out of stock only if it tracks inventory AND has none left.
// Untracked products are always "in stock".
const isOutOfStock = (product: Product): boolean => {
    return product.track_inventory && Number(product.current_stock) <= 0;
};
</script>

<template>
    <ProductsNav current-page="products" />
    
    <AppPageHeader
        resourceName="Products"
        v-model="search"
        search-placeholder="Search by name, sku or barcode..."
        create-url="/products/create"
        create-label="Product"
        @search="handleSearch"
    />

    <!-- Duplicate warning banner -->
    <div v-if="duplicateCount > 0" class="duplicate-warning-banner mb-4">
        <div class="flex items-center gap-2 text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
            <AlertCircle class="w-5 h-5 shrink-0" />
            <span>
                <strong>{{ duplicateCount }}</strong> duplicate product{{ duplicateCount > 1 ? 's' : '' }} found. 
                Products with the same name, description, and price are highlighted in red.
                <span class="text-sm text-amber-600 ml-2">({{ duplicateSummary.uniqueGroups }} unique product{{ duplicateSummary.uniqueGroups > 1 ? 's' : '' }} with duplicates)</span>
            </span>
        </div>
    </div>

    <div class="md:hidden space-y-3">
        <div
            v-for="(product, index) in products.data"
            :key="product.id"
            class="border border-border rounded-lg p-4 bg-card shadow-sm"
            :class="{ 'bg-red-50 border-red-400': isOutOfStock(product) }"
        >
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs text-muted-foreground">
                        #{{ (products.meta.current_page - 1) * products.meta.per_page + index + 1 }}
                    </p>
                    <p class="font-semibold text-base">{{ product.name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="productRoutes.edit(product.uuid).url" class="action edit p-2">
                        <Pencil class="w-4 h-4 text-green-600" />
                    </Link>
                    <DeleteConfirmationDialog
                        :url="productRoutes.destroy(product.uuid).url"
                        title="Delete Product?"
                        description="This product will be deleted permanently!"
                        confirm-text="Delete Product"
                    >
                        <template #trigger>
                            <button class="action delete p-2">
                                <Trash2 class="w-4 h-4 text-red-600" />
                            </button>
                        </template>
                    </DeleteConfirmationDialog>
                </div>
            </div>

            <div class="space-y-1.5 text-sm mb-3">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Category:</span>
                    <span class="font-medium text-right">{{ product.category_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Stock Count:</span>
                    <span class="font-medium text-right">
                        {{ product.current_stock }}
                        <span
                            v-if="isOutOfStock(product)"
                            class="ml-1 inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700"
                        >
                            Out of Stock
                        </span>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Price:</span>
                    <span class="font-medium text-right">{{ formatPrice(product.price) }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-3 border-t border-border">
                <button
                    @click="toggleAttribute(product, 'is_featured')"
                    :disabled="isLoading(product, 'is_featured')"
                    :class="getTagClasses('is_featured', product.is_featured)"
                >
                    <Loader2 v-if="isLoading(product, 'is_featured')" class="w-3 h-3 animate-spin" />
                    <span v-else>Featured</span>
                </button>

                <button
                    @click="toggleAttribute(product, 'is_new')"
                    :disabled="isLoading(product, 'is_new')"
                    :class="getTagClasses('is_new', product.is_new)"
                >
                    <Loader2 v-if="isLoading(product, 'is_new')" class="w-3 h-3 animate-spin" />
                    <span v-else>New</span>
                </button>

                <button
                    @click="toggleAttribute(product, 'is_active')"
                    :disabled="isLoading(product, 'is_active')"
                    :class="getTagClasses('is_active', product.is_active)"
                >
                    <Loader2 v-if="isLoading(product, 'is_active')" class="w-3 h-3 animate-spin" />
                    <span v-else>Active</span>
                </button>

                <button
                    @click="toggleAttribute(product, 'track_inventory')"
                    :disabled="isLoading(product, 'track_inventory')"
                    :class="getTagClasses('track_inventory', product.track_inventory)"
                >
                    <Loader2 v-if="isLoading(product, 'track_inventory')" class="w-3 h-3 animate-spin" />
                    <span v-else> Track Inventory</span>
                </button>
            </div>
        </div>

        <div
            v-if="products.data.length === 0"
            class="border border-border rounded-lg p-8 text-center text-muted-foreground"
        >
            No products found.
        </div>
    </div>

    <div class="table-wrapper hidden md:block">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Image</TableHead>
                    <TableHead>Product</TableHead>
                    <TableHead>SKU</TableHead>
                    <TableHead>Barcode</TableHead>
                    <TableHead>Price (Ksh)</TableHead>
                    <TableHead>Inventory</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead class="tags">Tags</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow 
                    v-for="(product, index) in products.data" 
                    :key="product.id"
                    :class="{
                        'duplicate-product-row bg-red-50 hover:bg-red-100': isDuplicate(product.id),
                        'bg-red-50 border-l-4 border-red-500 hover:bg-red-100' : !isDuplicate(product.id) && isOutOfStock(product),
                    }"
                >
                    <TableCell class="id">
                        {{ (products.meta.current_page - 1) * products.meta.per_page + index + 1 }}
                    </TableCell>
                    <TableCell class="w-20">
                        <img :src="product.thumbnail_url" :alt="product.slug">
                    </TableCell>
                    <TableCell class="max-w-30 overflow-hidden text-ellipsis cursor-help" :title="product.name">
                        {{ product.name }}

                        <!-- Out of stock indicator -->
                        <span
                            v-if="isOutOfStock(product)"
                            class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700"
                        >
                            Out of stock
                        </span>

                        <!-- Duplicate indicator -->
                        <span v-if="isDuplicate(product.id)" class="ml-2 text-xs text-red-600 font-bold">
                            ⚠️ Duplicate
                        </span>
                    </TableCell>
                    <TableCell>{{ product.sku ?? '-' }}</TableCell>
                    <TableCell>{{ product.barcode ?? '-' }}</TableCell>
                    <TableCell>{{ formatPrice(product.price) }}</TableCell>
                    <TableCell :class="{'font-bold text-red-600' : isOutOfStock(product)}">{{ product.current_stock }}</TableCell>
                    <TableCell>{{ product.category_name }}</TableCell>
                    <TableCell class="tags min-w-50 w-45">
                        <div class="tags-wrapper flex flex-wrap gap-4">
                            <!-- Featured -->
                            <button
                                @click="toggleAttribute(product, 'is_featured')"
                                :disabled="isLoading(product, 'is_featured')"
                                :class="getTagClasses('is_featured', product.is_featured)"
                            >
                                <Loader2 v-if="isLoading(product, 'is_featured')" class="w-3 h-3 animate-spin" />
                                <span v-else>Featured</span>
                            </button>

                            <!-- New -->
                            <button
                                @click="toggleAttribute(product, 'is_new')"
                                :disabled="isLoading(product, 'is_new')"
                                :class="getTagClasses('is_new', product.is_new)"
                            >
                                <Loader2 v-if="isLoading(product, 'is_new')" class="w-3 h-3 animate-spin" />
                                <span v-else>New</span>
                            </button>

                            <!-- Active -->
                            <button
                                @click="toggleAttribute(product, 'is_active')"
                                :disabled="isLoading(product, 'is_active')"
                                :class="getTagClasses('is_active', product.is_active)"
                            >
                                <Loader2 v-if="isLoading(product, 'is_active')" class="w-3 h-3 animate-spin" />
                                <span v-else>Active</span>
                            </button>

                            <button
                                @click="toggleAttribute(product, 'track_inventory')"
                                :disabled="isLoading(product, 'track_inventory')"
                                :class="getTagClasses('track_inventory', product.track_inventory)"
                            >
                                <Loader2 v-if="isLoading(product, 'track_inventory')" class="w-3 h-3 animate-spin" />
                                <span v-else> Track Inventory</span>
                            </button>
                        </div>
                    </TableCell>
                    <TableCell class="actions w-20">
                        <div class="actions-wrapper">
                            <Link :href="productRoutes.edit(product.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog 
                                :url="productRoutes.destroy(product.uuid).url" 
                                title="Delete Product?" 
                                description="This product will be deleted permanently!" 
                                confirm-text="Delete Product"
                            >
                                <template #trigger>
                                    <button class="action delete">
                                        <Trash2 />
                                    </button>
                                </template>
                            </DeleteConfirmationDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="products.data.length === 0">
                    <TableCell colspan="12" class="blank-table-row">
                        No products found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <Pagination :meta="products.meta" />

    <div class="table-results-summary">
        <p>
            Showing {{ getDisplayRange.start }} to {{ getDisplayRange.end }}
            of {{ getDisplayRange.total }} products
        </p>
        <p v-if="hasActiveFilters" class="filtered-results">
            Filtered results
        </p>
        <p v-if="duplicateCount > 0" class="duplicate-summary">
            ⚠️ {{ duplicateCount }} duplicate{{ duplicateCount > 1 ? 's' : '' }} detected
        </p>
    </div>
</template>

<style scoped>
/* Duplicate product row styling */
.duplicate-product-row {
    background-color: rgb(254, 242, 242) !important; /* red-50 */
    border-left: 3px solid #dc2626; /* red-600 */
}

.duplicate-product-row:hover {
    background-color: rgb(254, 226, 226) !important; /* red-100 */
}

/* Duplicate badge styling */
.duplicate-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background-color: #fee2e2;
    color: #b91c1c;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Duplicate summary styling */
.duplicate-summary {
    color: #b91c1c;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Warning banner styling */
.duplicate-warning-banner {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
