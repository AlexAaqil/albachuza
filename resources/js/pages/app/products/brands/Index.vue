<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import productBrandRoutes from '@/routes/product-brands';
import ProductsNav from '../components/ProductsNav.vue';

interface ProductBrand {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    products_count: number;
};

interface Props {
    brands: ProductBrand[];
    search?: string;
};

const props = defineProps<Props>();

const search = ref(props.search || '');

const handleSearch = (value: string) => {
    router.get(productBrandRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Product Brands" />

    <ProductsNav current-page="product-brands" />

    <AppPageHeader
        resourceName="Product Brands"
        v-model="search"
        search-placeholder="Search by name..."
        create-url="/product-brands/create"
        create-label="Brand"
        @search="handleSearch"
    />

    <div class="md:hidden space-y-3">
        <div
            v-for="(brand, index) in brands"
            :key="brand.id"
            class="border border-border rounded-lg p-4 bg-card shadow-sm"
        >
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs text-muted-foreground">
                        #{{ index + 1 }}
                    </p>
                    <p class="font-semibold text-base">{{ brand.name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="productBrandRoutes.edit(brand.uuid).url" class="action edit p-2">
                        <Pencil class="w-4 h-4 text-green-600" />
                    </Link>
                    <DeleteConfirmationDialog
                        :url="productBrandRoutes.destroy(brand.uuid).url"
                        title="Delete Brand?"
                        description="This brand will be deleted permanently!"
                        confirm-text="Delete Brand"
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
                    <span class="text-muted-foreground">Products:</span>
                    <span class="font-medium text-right">{{ brand.products_count }}</span>
                </div>
            </div>
        </div>

        <div
            v-if="brands.length === 0"
            class="border border-border rounded-lg p-8 text-center text-muted-foreground"
        >
            No brands found.
        </div>
    </div>

    <div class="table-wrapper hidden md:block">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Slug</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(brand, index) in props.brands" :key="brand.id">
                    <TableCell class="id">{{ index + 1 }}</TableCell>
                    <TableCell>{{ brand.name }}</TableCell>
                    <TableCell>{{ brand.slug }}</TableCell>
                    <TableCell class="actions">
                        <div class="actions-wrapper">
                            <Link :href="productBrandRoutes.edit(brand.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog :url="productBrandRoutes.destroy(brand.uuid).url" title="Delete Brand?" description="This brand will be deleted permanently!" confirm-text="Delete Brand">
                                <template #trigger>
                                    <button class="action delete">
                                        <Trash2 />
                                    </button>
                                </template>
                            </DeleteConfirmationDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="props.brands.length === 0">
                    <TableCell colspan="5" class="blank-table-row">
                        No brands found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>