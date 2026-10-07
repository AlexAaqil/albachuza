<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import productCategoryRoutes from '@/routes/product-categories';
import ProductsNav from '../components/ProductsNav.vue';

interface ProductCategory {
    id: number;
    uuid: string;
    name: string;
    slug: string;
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
        resourceName="Product Categories"
        v-model="search"
        search-placeholder="Search by name..."
        create-url="/product-categories/create"
        create-label="Category"
        @search="handleSearch"
    />

    <div class="md:hidden space-y-3">
        <div
            v-for="(category, index) in categories"
            :key="category.id"
            class="border border-border rounded-lg p-4 bg-card shadow-sm"
        >
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs text-muted-foreground">
                        #{{ index + 1 }}
                    </p>
                    <p class="font-semibold text-base">{{ category.name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="productCategoryRoutes.edit(category.uuid).url" class="action edit p-2">
                        <Pencil class="w-4 h-4 text-green-600" />
                    </Link>
                    <DeleteConfirmationDialog
                        :url="productCategoryRoutes.destroy(category.uuid).url"
                        title="Delete Category?"
                        description="This category will be deleted permanently!"
                        confirm-text="Delete Category"
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
                    <span class="font-medium text-right">{{ category.products_count }}</span>
                </div>
            </div>
        </div>

        <div
            v-if="categories.length === 0"
            class="border border-border rounded-lg p-8 text-center text-muted-foreground"
        >
            No categories found.
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
                <TableRow v-for="(category, index) in props.categories" :key="category.id">
                    <TableCell class="id">{{ index + 1 }}</TableCell>
                    <TableCell>{{ category.name }}</TableCell>
                    <TableCell>{{ category.slug }}</TableCell>
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