<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { watch, computed, reactive, ref } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import Pagination from '@/components/custom/Pagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import orderRoutes from '@/routes/orders';

const { formatPrice } = usePriceFormatter();

interface Order {
    id: number;
    uuid: string;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    delivery_address: string;
    total_selling_price: number;
    amount_paid: number;
    payment_status: string;
    order_status: string;
    order_status_label: string;
    delivery_status: string;
    delivery_status_label: string;
    created_by?: {
        id: number;
        name: string;
    } | null;
    updated_by?: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    orders: {
        data: Order[];
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
        created_by?: string;
        delivery_method?: string;
        payment_status?: string;
        from?: string;
        to?: string;
    };
    statuses: {value:string; label: string}[];
    cashiers: { id: number; name: string }[];
}

const props = defineProps<Props>();

const isSearching = ref(false);

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400); // 400ms feels right; 300 is snappier, 500 is safer on slow links
}

const filters = reactive({
    search:          props.filters?.search          ?? '',
    status:          props.filters?.status          ?? '',
    created_by:      props.filters?.created_by      ?? '',
    delivery_method: props.filters?.delivery_method ?? '',
    payment_status:  props.filters?.payment_status  ?? '',
    from:            props.filters?.from            ?? '',
    to:              props.filters?.to              ?? '',
});

watch(() => props.filters, (next) => {
    Object.assign(filters, {
        // search: next.search ?? '', ← DON'T sync search back
        status:          next.status          ?? '',
        created_by:      next.created_by      ?? '',
        delivery_method: next.delivery_method ?? '',
        payment_status:  next.payment_status  ?? '',
        from:            next.from            ?? '',
        to:              next.to              ?? '',
    });
}, { deep: true });

function applyFilters() {
    const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    );

    router.get(orderRoutes.index().url, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    clearTimeout(searchTimeout);

    // Reset local state
    filters.search          = '';
    filters.status          = '';
    filters.created_by      = '';
    filters.delivery_method = '';
    filters.payment_status  = '';
    filters.from            = '';
    filters.to              = '';

    // Navigate to the clean URL
    router.get(orderRoutes.index().url, {}, {
        preserveState: true,
        replace: true,
    });
}

const getDisplayRange = computed(() => {
    const { current_page, per_page, total } = props.orders.meta;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);

    return { start, end, total };
});

const hasActiveFilters = computed(() =>
    Object.values(filters).some(v => v !== '' && v !== null && v !== undefined)
);

// Helper to get status color classes
const getOrderStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        'pending': 'text-yellow-600',
        'confirmed': 'text-blue-600',
        'processing': 'text-indigo-600',
        'ready_for_pickup': 'text-purple-600',
        'completed': 'text-green-600',
        'cancelled': 'text-red-600',
        'refunded': 'text-gray-600',
    };

    return colors[status] || 'text-gray-600';
};

const getDeliveryStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        'pending': 'text-yellow-600',
        'picked_up': 'text-green-600',
        'in_transit': 'text-indigo-600',
        'out_for_delivery': 'text-purple-600',
        'delivered': 'text-green-600',
        'delivery_failed': 'text-red-600',
        'returned': 'text-gray-600',
    };

    return colors[status] || 'text-gray-600';
};

const getPaymentStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        'paid': 'text-green-600',
        'pending': 'text-yellow-600',
        'partially_paid': 'text-blue-600',
        'failed': 'text-red-600',
    };

    return colors[status] || 'text-gray-600';
};
</script>

<template>
    <Head title="Orders" />

    <AppPageHeader
        resourceName="Orders"
        v-model="filters.search"
        search-placeholder="Search by order number or customer phone number..."
        :create-url="orderRoutes.create().url"
        create-label="Order"
        @search="onSearchInput"
    />

    <div class="filters grid grid-cols-2 gap-4 lg:grid-cols-7">
        <select v-model="filters.status" @change="applyFilters" class="border border-border p-2 rounded-sm">
            <option value="">All statuses</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>

        <select v-model="filters.created_by" @change="applyFilters" class="border border-border p-2 rounded-sm">
            <option value="">All cashiers</option>
            <option v-for="c in cashiers" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>

        <select v-model="filters.delivery_method" @change="applyFilters" class="border border-border p-2 rounded-sm">
            <option value="">All methods</option>
            <option value="shop">Shop pickup</option>
            <option value="delivery">Delivery</option>
        </select>

        <select v-model="filters.payment_status" @change="applyFilters" class="border border-border p-2 rounded-sm">
            <option value="">All payments</option>
            <option value="paid">Paid</option>
            <option value="partially_paid">Partially paid</option>
            <option value="unpaid">Unpaid</option>
        </select>

        <input type="date" v-model="filters.from" @change="applyFilters" class="border border-border p-2 rounded-sm" />
        <input type="date" v-model="filters.to" @change="applyFilters" class="border border-border p-2 rounded-sm" />

        <button
            v-if="hasActiveFilters"
            type="button"
            class="clear-filters bg-red-600 text-white font-medium p-2 rounded-sm"
            @click="clearFilters"
        >
            Clear filters
        </button>
    </div>

    <!-- Mobile Card View (shown on small screens) -->
    <div class="md:hidden space-y-3">
        <div
            v-for="(order, index) in orders.data"
            :key="order.id"
            class="border border-border rounded-lg p-4 bg-card shadow-sm"
        >
            <!-- Header: Order number + Actions -->
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="font-semibold text-base">{{ order.order_number }}</p>
                    <p class="text-xs text-muted-foreground">
                        #{{ (orders.meta.current_page - 1) * orders.meta.per_page + index + 1 }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="orderRoutes.edit(order.uuid).url" class="action edit p-2">
                        <Pencil class="w-4 h-4 text-green-600" />
                    </Link>
                    <DeleteConfirmationDialog
                        :url="orderRoutes.destroy(order.uuid).url"
                        title="Delete Order?"
                        description="This order will be deleted permanently!"
                        confirm-text="Delete Order"
                    >
                        <template #trigger>
                            <button class="action delete p-2">
                                <Trash2 class="w-4 h-4 text-red-600" />
                            </button>
                        </template>
                    </DeleteConfirmationDialog>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="space-y-1.5 text-sm mb-3">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Customer:</span>
                    <span class="font-medium text-right">{{ order.customer_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Phone:</span>
                    <span class="font-medium text-right">{{ order.customer_phone ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Amount Paid:</span>
                    <span class="font-medium text-right">{{ formatPrice(order.amount_paid) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Cashier:</span>
                    <span class="font-medium text-right">{{ order.created_by?.name ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Status Badges -->
            <div class="flex flex-wrap gap-2 pt-3 border-t border-border">
                <span
                    class="text-xs px-2 py-1 rounded-full bg-muted"
                    :class="getPaymentStatusColor(order.payment_status)"
                >
                    {{ order.payment_status }}
                </span>
                <span
                    class="text-xs px-2 py-1 rounded-full bg-muted"
                    :class="getOrderStatusColor(order.order_status)"
                >
                    {{ order.order_status_label }}
                </span>
                <span
                    class="text-xs px-2 py-1 rounded-full bg-muted"
                    :class="getDeliveryStatusColor(order.delivery_status)"
                >
                    {{ order.delivery_status_label || 'N/A' }}
                </span>
            </div>
        </div>

        <!-- Empty state for mobile -->
        <div
            v-if="orders.data.length === 0"
            class="border border-border rounded-lg p-8 text-center text-muted-foreground"
        >
            No orders found.
        </div>
    </div>

    <div class="table-wrapper hidden md:block">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Order</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Phone Number</TableHead>
                    <TableHead>Amount Paid</TableHead>
                    <TableHead>Payment</TableHead>
                    <TableHead>Order</TableHead>
                    <TableHead>Delivery</TableHead>
                    <TableHead>Cashier</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(order, index) in orders.data" :key="order.id">
                    <TableCell class="id">{{ (orders.meta.current_page - 1) * orders.meta.per_page + index + 1 }}</TableCell>
                    <TableCell>{{ order.order_number }}</TableCell>
                    <TableCell>{{ order.customer_name }}</TableCell>
                    <TableCell>{{ order.customer_phone ?? '-' }}</TableCell>
                    <TableCell>{{ formatPrice(order.amount_paid) }}</TableCell>
                    <TableCell :class="getPaymentStatusColor(order.payment_status)">
                        {{ order.payment_status }}
                    </TableCell>
                    <TableCell :class="getOrderStatusColor(order.order_status)">
                        {{ order.order_status_label }}
                    </TableCell>
                    <TableCell :class="getDeliveryStatusColor(order.delivery_status)">
                        {{ order.delivery_status_label || 'N/A' }}
                    </TableCell>
                    <TableCell>{{ order.created_by?.name ?? 'N/A' }}</TableCell>
                    <TableCell class="actions w-20">
                        <div class="actions-wrapper">
                            <Link :href="orderRoutes.edit(order.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog 
                                :url="orderRoutes.destroy(order.uuid).url" 
                                title="Delete Order?" 
                                description="This order will be deleted permanently!" 
                                confirm-text="Delete Order"
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

                <TableRow v-if="orders.data.length === 0">
                    <TableCell colspan="20" class="blank-table-row">
                        No orders found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <Pagination :meta="orders.meta" />

    <div class="table-results-summary">
        <p>
            Showing {{ getDisplayRange.start }} to {{ getDisplayRange.end }}
            of {{ getDisplayRange.total }} orders
        </p>
        <p v-if="hasActiveFilters" class="filtered-results">
            Filtered results
        </p>
    </div>
</template>
