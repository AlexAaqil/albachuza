<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import brandRoutes from '@/routes/product-brands';
import ProductsNav from '../components/ProductsNav.vue';

interface Brand {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    is_active: boolean;
    image_url: string;
    products_count: number;
};

interface Props {
    brands: Brand[];
    search?: string;
};

const props = defineProps<Props>();

const search = ref(props.search || '');

const handleSearch = (value: string) => {
    router.get(brandRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Brands" />

    <ProductsNav current-page="product-brands" />

    <AppPageHeader
        resourceName="Brands"
        v-model="search"
        search-placeholder="Search by name..."
        create-url="/product-brands/create"
        create-label="Brand"
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
                <TableRow v-for="(brand, index) in props.brands" :key="brand.id">
                    <TableCell class="id">{{ index + 1 }}</TableCell>
                    <TableCell class="w-20"><img :src="brand.image_url" :alt="brand.slug"></TableCell>
                    <TableCell :class="!brand.is_active ? 'text-red-600 font-bold' : ''">{{ brand.name }}</TableCell>
                    <TableCell>{{ brand.products_count }}</TableCell>
                    <TableCell class="actions">
                        <div class="actions-wrapper">
                            <Link :href="brandRoutes.edit(brand.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog :url="brandRoutes.destroy(brand.uuid).url" title="Delete Category?" description="This brand will be deleted permanently!" confirm-text="Delete Category">
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