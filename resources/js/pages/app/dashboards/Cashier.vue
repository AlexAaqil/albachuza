<script setup lang="ts">
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardStat from './components/DashboardStat.vue';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
// import orderRoutes from '@/routes/orders';

const page = usePage();
const user = computed(() => page.props.auth.user);

const { formatPrice } = usePriceFormatter();

interface Props {
    stats: {
        today: {
            orders_count: number;
            sales_total: number;
            cash_collected: number;
            mpesa_collected: number;
        };
        needs_attention: {
            pending_payment: number;
            ready_for_pickup: number;
        };
        low_stock: Array<{
            id: number;
            name: string;
            current_stock: number;
        }>;
    };
}

const props = defineProps<Props>();

// Convenience: is anything needing attention?
const hasAttentionItems = computed(() =>
    props.stats.needs_attention.pending_payment > 0 ||
    props.stats.needs_attention.ready_for_pickup > 0
);
</script>

<template>
    <Head title="Cashier Dashboard" />

    <div class="Dashboard CashierDashboard space-y-8">
        <!-- Header -->
        <section class="header">
            <div class="flex items-center gap-4">
                <p>Hi {{ user.name }}</p>
                <span class="text-xs text-blue-900 bg-blue-100 py-1 px-2 rounded-sm">
                    {{ user.role_label }}
                </span>
            </div>
        </section>

        <!-- Today's Shift Snapshot -->
        <section class="stats-wrapper">
            <h2 class="mb-4 font-medium">Today's Shift</h2>

            <div class="stats grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <DashboardStat
                    :stat="stats.today.orders_count"
                    label="Orders Processed"
                    format="number"
                />

                <DashboardStat
                    :stat="stats.today.sales_total"
                    label="Sales Total"
                    format="currency"
                />

                <DashboardStat
                    :stat="stats.today.cash_collected"
                    label="Cash Collected"
                    format="currency"
                />

                <DashboardStat
                    :stat="stats.today.mpesa_collected"
                    label="M-Pesa Collected"
                    format="currency"
                />
            </div>
        </section>

        <!-- Needs Attention -->
        <section v-if="hasAttentionItems" class="attention-wrapper">
            <h2 class="mb-4 font-medium">Needs Your Attention</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-if="stats.needs_attention.pending_payment > 0"
                    :href="orderRoutes.index({ query: { status: 'pending' } }).url"
                    class="flex items-center justify-between rounded-lg border-l-4 border-orange-400 bg-orange-50 dark:bg-orange-950/30 p-4 hover:bg-orange-100 dark:hover:bg-orange-900/40 transition"
                >
                    <div>
                        <p class="text-sm font-medium text-orange-900 dark:text-orange-200">
                            Orders Pending Payment
                        </p>
                        <p class="text-xs text-orange-700 dark:text-orange-300">
                            Follow up with customers
                        </p>
                    </div>
                    <span class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                        {{ stats.needs_attention.pending_payment }}
                    </span>
                </Link>

                <Link
                    v-if="stats.needs_attention.ready_for_pickup > 0"
                    :href="orderRoutes.index({ query: { status: 'ready_for_pickup' } }).url"
                    class="flex items-center justify-between rounded-lg border-l-4 border-purple-400 bg-purple-50 dark:bg-purple-950/30 p-4 hover:bg-purple-100 dark:hover:bg-purple-900/40 transition"
                >
                    <div>
                        <p class="text-sm font-medium text-purple-900 dark:text-purple-200">
                            Ready for Pickup
                        </p>
                        <p class="text-xs text-purple-700 dark:text-purple-300">
                            Hand off to customers
                        </p>
                    </div>
                    <span class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ stats.needs_attention.ready_for_pickup }}
                    </span>
                </Link>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="actions-wrapper">
            <h2 class="mb-4 font-medium">Quick Actions</h2>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="orderRoutes.create().url"
                    class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition"
                >
                    + New Order (POS)
                </Link>

                <Link
                    :href="orderRoutes.index().url"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                >
                    View My Orders
                </Link>
            </div>
        </section>

        <!-- Low Stock -->
        <section v-if="stats.low_stock.length > 0" class="low-stock-wrapper">
            <h2 class="mb-4 font-medium flex items-center gap-2">
                <span>Low Stock</span>
                <span class="text-xs bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 px-2 py-0.5 rounded-full">
                    {{ stats.low_stock.length }}
                </span>
            </h2>

            <div class="rounded-lg border bg-card">
                <ul class="divide-y">
                    <li
                        v-for="product in stats.low_stock"
                        :key="product.id"
                        class="flex items-center justify-between px-4 py-3 text-sm"
                    >
                        <span class="font-medium truncate">{{ product.name }}</span>
                        <span
                            class="ml-3 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="product.current_stock === 0
                                ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                                : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'"
                        >
                            {{ product.current_stock === 0 ? 'Out of stock' : `${product.current_stock} left` }}
                        </span>
                    </li>
                </ul>
            </div>

            <p class="text-xs text-muted-foreground mt-2">
                Let an admin know so they can restock.
            </p>
        </section>

        <!-- All good -->
        <section
            v-else
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            ✓ No low stock items. Everything looks good.
        </section>
    </div>
</template>