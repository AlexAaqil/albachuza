<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref, computed } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import Pagination from '@/components/custom/Pagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import expenseRoutes from '@/routes/expenses';
import ExpensesNav from '../components/ExpensesNav.vue';

const { formatPrice } = usePriceFormatter();

interface Expense {
    id: number;
    uuid: string;
    amount: number;
    expense_date_formatted: string;
    category_name: string;
};

interface Props {
    expenses: {
        data: Expense[];
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
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');

const handleSearch = (value: string) => {
    router.get(expenseRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const getDisplayRange = computed(() => {
    const { current_page, per_page, total } = props.expenses.meta;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);

    return { start, end, total };
});

const hasActiveFilters = computed(() => !!search.value);
</script>

<template>
    <Head title="Expenses" />

    <ExpensesNav current-page="expenses" />

    <AppPageHeader
        resourceName="Expenses"
        v-model="search"
        search-placeholder="Search by category..."
        create-url="/expenses/create"
        create-label="Expense"
        @search="handleSearch"
    />

    <div class="table-wrapper">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(expense, index) in expenses.data" :key="expense.id">
                    <TableCell class="id">{{ index + 1 }}</TableCell>
                    <TableCell>{{ expense.category_name }}</TableCell>
                    <TableCell>{{ formatPrice(expense.amount) }}</TableCell>
                    <TableCell>{{ expense.expense_date_formatted }}</TableCell>
                    <TableCell class="actions">
                        <div class="actions-wrapper">
                            <Link :href="expenseRoutes.edit(expense.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog :url="expenseRoutes.destroy(expense.uuid).url" title="Delete Expense?" description="This expense will be deleted permanently!" confirm-text="Delete Expense">
                                <template #trigger>
                                    <button class="action delete">
                                        <Trash2 />
                                    </button>
                                </template>
                            </DeleteConfirmationDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="expenses.data.length === 0">
                    <TableCell colspan="5" class="blank-table-row">
                        No expenses found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <Pagination :meta="expenses.meta" />

    <div class="table-results-summary">
        <p>
            Showing {{ getDisplayRange.start }} to {{ getDisplayRange.end }}
            of {{ getDisplayRange.total }} expenses
        </p>
        <p v-if="hasActiveFilters" class="filtered-results">
            Filtered results
        </p>
    </div>
</template>