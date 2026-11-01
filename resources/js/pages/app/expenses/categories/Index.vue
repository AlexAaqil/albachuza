<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import expenseCategoryRoutes from '@/routes/expense-categories';
import ExpensesNav from '../components/ExpensesNav.vue';

interface ExpenseCategory {
    id: number;
    uuid: string;
    name: string;
    expense_count: number;
    total_amount: number;
};

interface Props {
    categories: {
        data: ExpenseCategory[];
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
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');

const handleSearch = (value: string) => {
    router.get(expenseCategoryRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Expense Categories" />

    <ExpensesNav current-page="expense-categories" />

    <AppPageHeader
        resourceName="Expense Categories"
        v-model="search"
        search-placeholder="Search by name..."
        create-url="/expense-categories/create"
        create-label="Category"
        @search="handleSearch"
    />

    <div class="table-wrapper">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Expenses</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(category, index) in categories.data" :key="category.id">
                    <TableCell class="id">{{ index + 1 }}</TableCell>
                    <TableCell>{{ category.name }}</TableCell>
                    <TableCell>{{ category.expense_count }}</TableCell>
                    <TableCell>{{ category.total_amount }}</TableCell>
                    <TableCell class="actions">
                        <div class="actions-wrapper">
                            <Link :href="expenseCategoryRoutes.edit(category.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog :url="expenseCategoryRoutes.destroy(category.uuid).url" title="Delete Category?" description="This category will be deleted permanently!" confirm-text="Delete Category">
                                <template #trigger>
                                    <button class="action delete">
                                        <Trash2 />
                                    </button>
                                </template>
                            </DeleteConfirmationDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="categories.data.length === 0">
                    <TableCell colspan="5" class="blank-table-row">
                        No categories found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>