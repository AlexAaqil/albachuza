<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, ArcElement, PointElement, DoughnutController, LineController } from 'chart.js';
import { computed } from 'vue';
import { Line, Doughnut } from 'vue-chartjs';
import DashboardStat from './components/DashboardStat.vue';

ChartJS.register(
    Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale,
    ArcElement, PointElement, DoughnutController, LineController,
);

const page = usePage();
const user = computed(() => page.props.auth.user);

type Period = 'today' | 'week' | 'month' | 'year';

interface Delta { 
    revenue: number | null; 
    orders: number | null; 
    expenses?: number | null; 
    net_profit?: number | null; 
}

interface TopProduct {
    id: number;
    name: string;
    qty: number;
    revenue: number;
}

interface Stats {
    period: Period;
    periods: Period[];

    glance: {
        today: { revenue: number; orders: number; delta: Delta };
        needs_attention: number;
        low_stock_count: number;
    };

    trend: { labels: string[]; values: number[] };

    payment_breakdown: { mpesa: number; cash: number };
    status_mix: Record<string, number>;
    top_products: TopProduct[];

    fiscal: {
        orders: number;
        revenue: number;
        cogs: number;
        gross_profit: number;
        margin: number;
        expenses: number;
        net_profit: number;
        net_margin: number;
        delta: Delta;
        aov: number;
    };

    deltas: Record<Period, Delta>;
    counts: {
        total_users: number;
        total_admins: number;
        total_cashiers: number;
        total_products: number;
        total_product_categories: number;
        total_orders: number;
        orders_need_attention: number;
        delivery_locations: number;
        delivery_areas: number;
        callback_requests: number;
        unread_callback_requests: number;
    };

    system?: SystemStats;

    expense_breakdown: Record<string, number>;
}

interface SystemStats {
    app: { 
        name: string; 
        env: string; 
        debug: boolean; 
        url: string; 
        timezone: string;
        config_cached: boolean; 
        routes_cached: boolean; 
        maintenance: boolean;
    };
    drivers: { cache: string; queue: string; session: string; mail: string };
    warnings: string[];
    versions: { laravel: string; php: string; sapi: string };
    runtime: { memory_usage: number; memory_peak: number; opcache: boolean };
    packages: Record<string, string>;
    server: { os: string; software: string | null };
    database: { ok: boolean; driver: string; ping_ms?: number; error?: string };
}

const props = defineProps<{ 
    stats: Stats;
}>();

const periodLabel: Record<Period, string> = {
    today: 'Today',
    week: 'This Week',
    month: 'This Month',
    year: 'This Year',
};

function setPeriod(period: Period) {
    if (period === props.stats.period) {
        return;
    }

    router.get(
        window.location.pathname,
        { period },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// ── Trend chart ─────────────────────────────────────────────────────────
const lineChartData = computed(() => ({
    labels: props.stats.trend.labels,
    datasets: [{
        label: 'Sales (Ksh)',
        data: props.stats.trend.values,
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.3,
    }],
}));

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: any) => `Ksh ${ctx.parsed.y.toLocaleString()}`,
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { callback: (v: any) => `${v.toLocaleString()}` },
        },
    },
};

// ── Payment donut ───────────────────────────────────────────────────────
const paymentData = computed(() => ({
    labels: ['M-Pesa', 'Cash'],
    datasets: [{
        data: [props.stats.payment_breakdown.mpesa, props.stats.payment_breakdown.cash],
        backgroundColor: ['#10b981', '#f59e0b'],
        borderWidth: 1,
    }],
}));

const paymentOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' as const },
        tooltip: {
            callbacks: {
                label: (ctx: any) => {
                    const total = ctx.dataset.data.reduce((a: number, b: number) => a + b, 0);
                    const pct = total === 0 ? 0 : ((ctx.parsed / total) * 100).toFixed(1);

                    return `Ksh ${ctx.parsed.toLocaleString()} (${pct}%)`;
                },
            },
        },
    },
};

// ── Formatting helpers ──────────────────────────────────────────────────
const fmtCurrency = (n: number) => `Ksh ${n.toLocaleString()}`;
const fmtPct = (n: number | null) =>
    n === null ? 'n/a' : `${n >= 0 ? '+' : ''}${n.toFixed(1)}%`;
</script>

<template>
    <Head title="S.Admin Dashboard" />

    <div class="Dashboard space-y-8">
        <section class="header">
            <div class="flex items-center gap-4">
                <p>Hi {{ user.name }}</p>
                <span class="text-xs text-blue-900 bg-blue-100 py-1 px-2 rounded-sm">
                    {{ user.role_label }}
                </span>
            </div>
        </section>

        <section class="TodayStats glance">
            <h2 class="mb-4 font-medium">Today at a glance</h2>
            <div class="stats grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <DashboardStat
                    :stat="stats.glance.today.revenue"
                    format="currency"
                    label="Today's Revenue"
                >
                    <template #extras>
                        <span
                            class="text-sm"
                            :class="(stats.glance.today.delta.revenue ?? 0) >= 0
                                ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ fmtPct(stats.glance.today.delta.revenue) }} vs yesterday
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat
                    :stat="stats.glance.today.orders"
                    label="Today's Orders"
                >
                    <template #extras>
                        <span
                            class="text-sm"
                            :class="(stats.glance.today.delta.orders ?? 0) >= 0
                                ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ fmtPct(stats.glance.today.delta.orders) }} vs yesterday
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat
                    :stat="stats.glance.needs_attention"
                    label="Needs Attention"
                    variant="danger"
                >
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            Pending / stuck orders
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat
                    :stat="stats.glance.low_stock_count"
                    label="Low Stock Items"
                >
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            At or below threshold
                        </span>
                    </template>
                </DashboardStat>
            </div>
        </section>

        <section class="Performance flex flex-col lg:flex-row gap-4 lg:items-center lg:justify-between">
            <h2 class="font-medium">Performance</h2>
            <div class="flex gap-1 p-1 bg-muted rounded-md">
                <button
                    v-for="p in stats.periods"
                    :key="p"
                    type="button"
                    class="px-3 py-1.5 text-sm rounded-sm transition"
                    :class="stats.period === p
                        ? 'bg-background shadow-sm font-medium'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="setPeriod(p)"
                >
                    {{ periodLabel[p] }}
                </button>
            </div>
        </section>

        <section class="SalesTrend grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-background p-4 rounded-lg border border-border h-80">
                <h3 class="text-sm font-medium mb-2">
                    Sales trend - {{ periodLabel[stats.period] }}
                </h3>
                <div class="h-65">
                    <Line
                        :data="lineChartData"
                        :options="lineChartOptions"
                        style="height: 100%!important; width: 100%!important;"
                    />
                </div>
            </div>

            <div class="bg-background p-4 rounded-lg border border-border h-80">
                <h3 class="text-sm font-medium mb-2">Payment methods</h3>
                <div class="h-65">
                    <Doughnut
                        :data="paymentData"
                        :options="paymentOptions"
                        style="height: 100%!important; width: 100%!important;"
                    />
                </div>
            </div>
        </section>

        <section class="FiscalSnapshot stats-wrapper">
            <h2 class="mb-4 font-medium">
                Fiscal snapshot - {{ periodLabel[stats.period] }}
            </h2>

            <div class="stats grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <DashboardStat :stat="stats.fiscal.revenue" format="currency" label="Revenue">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">{{ stats.fiscal.orders }} completed orders</span>
                    </template>
                </DashboardStat>
                <DashboardStat :stat="stats.fiscal.cogs" format="currency" label="COGS" variant="danger" />
                <DashboardStat :stat="stats.fiscal.gross_profit" format="currency" label="Gross Profit" variant="success" />
                <DashboardStat :stat="stats.fiscal.margin" format="percent" label="Gross Margin" />

                <DashboardStat :stat="stats.fiscal.expenses" format="currency" label="Operating Expenses" variant="danger">
                    <template #extras>
                        <!-- for expenses, an increase is bad, so the colours are inverted -->
                        <span class="text-sm" :class="(stats.fiscal.delta.expenses ?? 0) <= 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ fmtPct(stats.fiscal.delta.expenses ?? null) }} vs previous
                        </span>
                    </template>
                </DashboardStat>
                <DashboardStat
                    :stat="stats.fiscal.net_profit"
                    format="currency"
                    label="Net Profit"
                    :variant="stats.fiscal.net_profit >= 0 ? 'success' : 'danger'"
                >
                    <template #extras>
                        <span class="text-sm" :class="(stats.fiscal.delta.net_profit ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ fmtPct(stats.fiscal.delta.net_profit ?? null) }} vs previous
                        </span>
                    </template>
                </DashboardStat>
                <DashboardStat :stat="stats.fiscal.net_margin" format="percent" label="Net Margin" />
                <DashboardStat :stat="stats.fiscal.aov" format="currency" label="AOV / ATV" />
            </div>
        </section>

        <section class="Products_Expenses grid lg:grid-cols-2 gap-8">
            <div class="bg-background p-4 rounded-lg border border-border">
                <h3 class="text-sm font-medium mb-3">
                    Top products - {{ periodLabel[stats.period] }}
                </h3>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(product, i) in stats.top_products"
                        :key="product.id ?? product.name"
                        class="flex items-center justify-between gap-3 py-1.5 border-b border-border/50 last:border-0"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-muted-foreground w-4">{{ i + 1 }}.</span>
                            <span class="truncate">{{ product.name }}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-medium">{{ fmtCurrency(product.revenue) }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ product.qty }} sold
                            </div>
                        </div>
                    </li>
                    <li
                        v-if="stats.top_products.length === 0"
                        class="text-sm text-muted-foreground py-2"
                    >
                        No sales in this period.
                    </li>
                </ul>
            </div>

            <div class="bg-background p-4 rounded-lg border border-border">
                <h3 class="text-sm font-medium mb-3">Expenses by category - {{ periodLabel[stats.period] }}</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="(total, name) in stats.expense_breakdown" :key="name"
                        class="flex justify-between py-1.5 border-b border-border/50 last:border-0">
                        <span class="truncate">{{ name }}</span>
                        <span class="font-medium">{{ fmtCurrency(total) }}</span>
                    </li>
                    <li v-if="!Object.keys(stats.expense_breakdown).length" class="text-muted-foreground py-2">
                        No expenses recorded in this period.
                    </li>
                </ul>
            </div>
        </section>

        <section class="PlatformStatistics stats-wrapper">
            <h2 class="mb-4 font-medium">Platform Statistics</h2>
            
            <div class="stats grid gap-8 lg:grid-cols-5">
                <DashboardStat :stat="stats.counts.total_users" label="Users">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.counts.total_admins }} Admins & {{ stats.counts.total_cashiers }} Cashiers
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.counts.total_orders" label="Orders">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.counts.orders_need_attention }} need attention
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.counts.total_products" label="Products">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.counts.total_product_categories }} Categories
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.counts.delivery_locations" label="Locations">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.counts.delivery_areas }} Areas
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.counts.callback_requests" label="Callback Requests">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.counts.unread_callback_requests }} Unread
                        </span>
                    </template>
                </DashboardStat>
            </div>
        </section>

        <section v-if="stats.system" class="SystemStats space-y-4">
            <h2 class="font-medium">System</h2>

            <div v-if="stats.system.warnings.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 space-y-1">
                <p v-for="w in stats.system.warnings" :key="w">{{ w }}</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="bg-background p-4 rounded-lg border border-border">
                    <p class="text-sm text-muted-foreground">Laravel</p>
                    <p class="text-2xl font-semibold mt-1">{{ stats.system.versions.laravel }}</p>
                    <p class="text-xs text-muted-foreground mt-1">
                        PHP {{ stats.system.versions.php }} · {{ stats.system.versions.sapi }}
                    </p>
                </div>

                <div class="bg-background p-4 rounded-lg border border-border">
                    <p class="text-sm text-muted-foreground">Environment</p>
                    <p class="text-2xl font-semibold mt-1 capitalize">{{ stats.system.app.env }}</p>
                    <p class="text-xs mt-1"
                    :class="stats.system.app.debug ? 'text-amber-600' : 'text-muted-foreground'">
                        Debug {{ stats.system.app.debug ? 'ON' : 'off' }}
                    </p>
                </div>

                <div class="bg-background p-4 rounded-lg border border-border">
                    <p class="text-sm text-muted-foreground">Database</p>
                    <p class="text-2xl font-semibold mt-1"
                    :class="stats.system.database.ok ? 'text-emerald-600' : 'text-red-600'">
                        {{ stats.system.database.ok ? 'OK' : 'Down' }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ stats.system.database.driver }}
                        <span v-if="stats.system.database.ping_ms !== undefined">· {{ stats.system.database.ping_ms }}ms</span>
                    </p>
                </div>

                <div class="bg-background p-4 rounded-lg border border-border">
                    <p class="text-sm text-muted-foreground">Memory (this request)</p>
                    <p class="text-2xl font-semibold mt-1">
                        {{ (stats.system.runtime.memory_usage / 1024 / 1024).toFixed(1) }} MB
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                        Peak {{ (stats.system.runtime.memory_peak / 1024 / 1024).toFixed(1) }} MB
                    </p>
                </div>
            </div>

            <!-- Package versions -->
            <div v-if="Object.keys(stats.system.packages).length" class="bg-background p-4 rounded-lg border border-border">
                <h3 class="text-sm font-medium mb-2">Tracked packages</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 text-sm">
                    <div v-for="(version, name) in stats.system.packages" :key="name"
                        class="flex justify-between gap-4 py-1 border-b border-border/50 last:border-0">
                        <dt class="text-muted-foreground truncate">{{ name }}</dt>
                        <dd class="font-mono shrink-0">{{ version }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Server -->
            <p class="text-xs text-muted-foreground">
                {{ stats.system.server.os }}
                <span v-if="stats.system.server.software"> · {{ stats.system.server.software }}</span>
            </p>
        </section>
    </div>
</template>