<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import ProductsNav from '../components/ProductsNav.vue';
import productCategoryRoutes from '@/routes/product-categories';

interface ProductCategory {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    image_url: string;
    products_count: number;
};

interface Props {
    categories: ProductCategory[];
    search?: string;
};

const props = defineProps<Props>();

const search = ref(props.search || '');

const handleSearch = (value: string) => {
    router.get(productCategoryRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Product Categories" />

    <ProductsNav current-page="product-categories" />

    <AppPageHeader
        resourceName="Categories"
        v-model="search"
        search-placeholder="Search by name..."
        create-url="/product-categories/create"
        create-label="Category"
        @search="handleSearch"
    />

    <div class="table-wrapper">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Image</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Products</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(category, index) in props.categories" :key="category.id">
                    <TableCell class="id">{{ index + 1 }}</TableCell>
                    <TableCell class="w-20">
                        <img :src="category.image_url" :alt="category.slug">
                    </TableCell>
                    <TableCell>{{ category.name }}</TableCell>
                    <TableCell>{{ category.products_count }}</TableCell>
                    <TableCell class="actions">
                        <div class="actions-wrapper">
                            <Link :href="productCategoryRoutes.edit(category.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog :url="productCategoryRoutes.destroy(category.uuid).url" title="Delete Category?" description="This category will be deleted permanently!" confirm-text="Delete Category">
                                <template #trigger>
                                    <button class="action delete">
                                        <Trash2 />
                                    </button>
                                </template>
                            </DeleteConfirmationDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="props.categories.length === 0">
                    <TableCell colspan="5" class="blank-table-row">
                        No categories found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>