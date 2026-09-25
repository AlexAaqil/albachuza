<script setup lang="ts">
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePriceFormatter } from '@/composables/usePriceFormatter';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { formatPrice } = usePriceFormatter();

interface Order {
    id: number;
    order_number: string;
    total_amount: number;
    order_status: number | null;
    order_status_label: string;
    payment_status: number | null;
    payment_status_label: string;
    created_at: string;
};

interface Stats {
    total_orders: number;
    pending_orders: number;
    processing_orders: number;
    shipped_orders: number;
    delivered_orders: number;
    cancelled_orders: number;
    total_spent: number;
    recent_orders: {
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
}

const props = defineProps<{
    stats: Stats;
}>();

// Computed property for order stats text
const orderStatsText = computed(() => {
    const hasPending = props.stats.pending_orders > 0;
    const hasShipped = props.stats.shipped_orders > 0;

    if (hasPending && hasShipped) {
        return `${props.stats.pending_orders} pending & ${props.stats.shipped_orders} shipped`;
    }

    if (hasPending && !hasShipped) {
        return `${props.stats.pending_orders} pending`;
    }

    if (!hasPending && hasShipped) {
        return `${props.stats.shipped_orders} shipped`;
    }

    return `Total: ${props.stats.total_orders} orders`;
});

// Optional: Get active orders (pending, processing, shipped)
const activeOrdersCount = computed(() => {
    return props.stats.pending_orders + props.stats.processing_orders + props.stats.shipped_orders;
});

const activeOrdersText = computed(() => {
    if (activeOrdersCount.value === 0) {
        return null;
    }

    if (activeOrdersCount.value === 1) {
        return '1 active order';
    }

    return `${activeOrdersCount.value} active orders`;
});
</script>

<template>
    <Head title="Customer Dashboard" />

    <div class="app-container Dashboard CustomerDashboard">
        <div class="Hero">
            <h1>Welcome back {{ user.name }}</h1>
            <p class="text-gray-600 mt-2">Track your orders and activity</p>
        </div>

        <div class="stats-wrapper">
            <div class="stat">
                <div class="label">Total Orders</div>
                <div class="number">{{ stats.total_orders }}</div>
                <div class="extras">{{ orderStatsText }}</div>
            </div>

            <div class="stat">
                <div class="label">Total Spent</div>
                <div class="number">{{ formatPrice(stats.total_spent) }}</div>
                <div class="extras">Lifetime value</div>
            </div>

            <div class="stat">
                <div class="label">Active Orders</div>
                <div class="number">{{ activeOrdersCount }}</div>
                <div class="extras" v-if="activeOrdersText">{{ activeOrdersText }}</div>
                <div class="extras" v-else>No active orders</div>
            </div>
        </div>

        <div v-if="stats.recent_orders && stats.recent_orders.data.length > 0" class="recent-orders mt-8">
            <h2 class="text-xl font-semibold mb-1">Recent Orders</h2>
            <div class="table-wrapper recent-orders mt-8">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="id">#</TableHead>
                            <TableHead>Order Number</TableHead>
                            <TableHead>Total</TableHead>
                            <TableHead>Payment</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead class="actions">Actions</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="(order, index) in stats.recent_orders.data" :key="order.id">
                            <TableCell class="id">{{ (stats.recent_orders.meta.current_page - 1) * stats.recent_orders.meta.per_page + index + 1 }}</TableCell>
                            <TableCell>{{ order.order_number }}</TableCell>
                            <TableCell>{{ order.total_amount }}</TableCell>
                            <TableCell :class="{
                                    'font-semibold text-green-600' : order.payment_status_label === 'Paid',
                                    'font-semibold text-red-600' : order.payment_status_label === 'Cancelled',
                                }"
                            >
                                {{ order.payment_status_label }}
                            </TableCell>
                            <TableCell :class="{
                                    'font-semibold text-green-600' : order.order_status_label === 'Delivered',
                                    'font-semibold text-yellow-500' : order.order_status_label === 'Shipped',
                                }"
                            >
                                {{ order.order_status_label }}
                            </TableCell>
                            <TableCell>{{ order.created_at }}</TableCell>
                            <TableCell class="actions">
                                <div class="actions-wrapper">
                                    <Link href="route('orders.show', order.id)" class="view-link">
                                        View →
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="stats.recent_orders.data.length === 0">
                            <TableCell colspan="5" class="blank-table-row">
                                No orders found.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div v-if="stats.total_orders > 20" class="view-all mt-4">
            <Link href="route('orders.index')" class="text-blue-600 hover:underline text-center">
                View all {{ stats.total_orders }} orders →
            </Link>
        </div>

        <div v-else-if="stats.total_orders === 0" class="empty-state mt-8">
            <div class="empty-state-content">
                <p class="text-gray-600">You haven't placed any orders yet.</p>
                <Link href="/shop" class="btn-primary mt-4 inline-block">
                    Start Shopping →
                </Link>
            </div>
        </div>
    </div>
</template>
