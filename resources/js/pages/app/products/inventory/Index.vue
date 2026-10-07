<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { Package, AlertTriangle, CheckCircle, XCircle, TrendingUp, DollarSign } from '@lucide/vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Pagination from '@/components/custom/Pagination.vue';
import type { Product } from '@/types/product';
import productInventoryRoutes from '@/routes/products-inventory';
import ProductsNav from '../components/ProductsNav.vue';

const props = defineProps<{
    products: { data: Product[]; meta: any };
    stats: {
        total_products: number;
        low_stock_count: number;
        out_of_stock_count: number;
        total_value: number;
    };
    filters: { search?: string; stock_status?: string };
}>();

const search = ref(props.filters.search || '');
const stockStatusFilter = ref(props.filters.stock_status || '');

const debouncedSearch = useDebounceFn(() => {
    router.get(productInventoryRoutes.index().url, {
        search: search.value,
        stock_status: stockStatusFilter.value
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([search, stockStatusFilter], () => {
    debouncedSearch();
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES'
    }).format(amount);
};

const getStockIcon = (product: Product) => {
    if (!product.track_inventory) return TrendingUp;
    if (product.current_stock <= 0) return XCircle;
    if (product.current_stock <= product.low_stock_threshold) return AlertTriangle;
    return CheckCircle;
};

const getDisplayRange = computed(() => {
    const { current_page, per_page, total } = props.products.meta;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);
    return { start, end, total };
});

const hasActiveFilters = computed(() => 
    !!(search.value || stockStatusFilter.value)
);
</script>

<template>
    <Head title="Inventory Management" />

    <ProductsNav current-page="products-inventory" />

    <div class="inventory-movement-page">
        <div class="header py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="info">
                <h1 class="title text-lg font-medium">Inventory Management</h1>
                <p class="description text-muted-foreground text-sm">Manage stock levels for all products</p>
            </div>

            <div class="search">
                <Input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or SKU..."
                    class="search-input min-w-62.5"
                />
            </div>

            <div class="filters-bar">
                <select v-model="stockStatusFilter" class="status-filter">
                    <option value="">All Status</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="unlimited">Unlimited (No Tracking)</option>
                </select>
            </div>
        </div>

        <div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card flex items-center gap-4 p-4 border border-border rounded-lg shadow-sm">
                <div class="icon bg-blue-100 p-3 rounded-full">
                    <Package class="w-5 h-5 text-blue-600" />
                </div>
                <div class="info">
                    <div class="value text-2xl font-bold">{{ stats.total_products }}</div>
                    <div class="label text-sm text-muted-foreground">Total Products</div>
                </div>
            </div>

            <div class="card flex items-center gap-4 p-4 border border-border rounded-lg shadow-sm">
                <div class="icon bg-green-100 p-3 rounded-full">
                    <DollarSign class="w-5 h-5 text-green-600" />
                </div>
                <div class="stat-info">
                    <div class="value text-2xl font-bold">{{ formatCurrency(stats.total_value) }}</div>
                    <div class="label text-sm text-muted-foreground">Inventory Value</div>
                </div>
            </div>

            <div class="card flex items-center gap-4 p-4 border border-border rounded-lg shadow-sm">
                <div class="icon bg-orange-100 p-3 rounded-full">
                    <AlertTriangle class="w-5 h-5 text-orange-600" />
                </div>
                <div class="info">
                    <div class="value text-orange-600 text-2xl font-bold">{{ stats.low_stock_count }}</div>
                    <div class="label text-sm text-muted-foreground">Low Stock Items</div>
                </div>
            </div>

            <div class="card flex items-center gap-4 p-4 border border-border rounded-lg shadow-sm">
                <div class="icon bg-red-100 p-3 rounded-full">
                    <XCircle class="w-5 h-5 text-red-600" />
                </div>
                <div class="info">
                    <div class="value text-red-600 text-2xl font-bold">{{ stats.out_of_stock_count }}</div>
                    <div class="label text-sm text-muted-foreground">Out of Stock</div>
                </div>
            </div>
        </div>
        
        <div class="md:hidden space-y-3">
            <div
                v-for="(product, index) in products.data"
                :key="product.id"
                class="border border-border rounded-lg p-4 bg-card shadow-sm"
            >
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            #{{ (products.meta.current_page - 1) * products.meta.per_page + index + 1 }}
                        </p>
                        <p class="font-semibold text-base">{{ product.name }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        
                    </div>
                </div>

                <div class="space-y-1.5 text-sm mb-3">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Status:</span>
                        <span class="inline-flex rounded-full text-xs font-medium text-right" :class="product.stock_badge_class">
                            <component :is="getStockIcon(product)" class="w-3 h-3" />
                            {{ product.stock_status }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Current Stock:</span>
                        <span class="font-medium text-right" :class="{ 'font-bold text-red-600': product.current_stock === 0 }">
                            {{ product.track_inventory ? product.current_stock : '∞' }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Low Stock Threshold:</span>
                        <span class="font-medium text-right">{{ product.low_stock_threshold }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Cost Price:</span>
                        <span class="font-medium text-right">{{ formatCurrency(product.cost_price) }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Cost Price:</span>
                        <span class="font-medium text-right">{{ formatCurrency(product.price) }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-3 border-t border-border">
                    <Link 
                        :href="productInventoryRoutes.create({product: product.uuid})"
                        class="px-3 py-1 text-sm rounded-md transition bg-blue-600 text-white hover:bg-blue-700"
                        v-if="product.track_inventory"
                    >
                        + Add Stock
                    </Link>
                    <Link 
                        :href="productInventoryRoutes.edit({product: product.uuid})"
                        class="px-3 py-1 text-sm rounded-md transition bg-red-600 text-white hover:bg-red-700"
                        v-if="product.track_inventory"
                    >
                        - Remove
                    </Link>
                    <Link 
                        :href="productInventoryRoutes.history({product: product.uuid})"
                        class="px-3 py-1 text-sm rounded-md transition border border-border text-foreground hover:bg-foreground hover:text-background"
                        v-if="product.track_inventory"
                    >
                        History
                    </Link>
                    <span v-else class="text-xs text-gray-400">Stock tracking not enabled</span>
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
                        <TableHead>Product</TableHead>
                        <TableHead>SKU</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Current Stock</TableHead>
                        <TableHead>Threshold</TableHead>
                        <TableHead>Cost Price</TableHead>
                        <TableHead>Selling Price</TableHead>
                        <TableHead class="actions">Actions</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="product in products.data" :key="product.id">
                        <TableCell class="font-medium">{{ product.name }}</TableCell>
                        <TableCell class="text-sm text-gray-500">{{ product.sku || 'N/A' }}</TableCell>
                        <TableCell>
                            <div :class="product.stock_badge_class" 
                                 class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium">
                                <component :is="getStockIcon(product)" class="w-3 h-3" />
                                {{ product.stock_status }}
                            </div>
                        </TableCell>
                        <TableCell>
                            <span :class="{ 'font-bold text-red-600': product.current_stock === 0 }">
                                {{ product.track_inventory ? product.current_stock : '∞' }}
                            </span>
                        </TableCell>
                        <TableCell>{{ product.low_stock_threshold }}</TableCell>
                        <TableCell>{{ formatCurrency(product.cost_price) }}</TableCell>
                        <TableCell>{{ formatCurrency(product.price) }}</TableCell>
                        <TableCell class="actions">
                            <div class="action-buttons">
                                <Link 
                                    :href="productInventoryRoutes.create({product: product.uuid})"
                                    class="btn-sm btn-primary"
                                    v-if="product.track_inventory"
                                >
                                    + Add Stock
                                </Link>
                                <Link 
                                    :href="productInventoryRoutes.edit({product: product.uuid})"
                                    class="btn-sm btn-danger"
                                    v-if="product.track_inventory"
                                >
                                    - Remove
                                </Link>
                                <Link 
                                    :href="productInventoryRoutes.history({product: product.uuid})"
                                    class="btn-sm btn-outline"
                                    v-if="product.track_inventory"
                                >
                                    History
                                </Link>
                                <span v-else class="text-xs text-gray-400">Stock tracking not enabled</span>
                            </div>
                        </TableCell>
                    </TableRow>
                    
                    <TableRow v-if="products.data.length === 0">
                        <TableCell colspan="7" class="text-center py-8 text-gray-500">
                            No products found
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
        </div>
    </div>
</template>