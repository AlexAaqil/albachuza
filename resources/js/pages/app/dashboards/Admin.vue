<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import DashboardStat from './components/DashboardStat.vue';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, ArcElement, PointElement } from 'chart.js';
import { Line, Pie } from 'vue-chartjs';

const page = usePage();
const user = computed(() => page.props.auth.user);


ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, ArcElement, PointElement);

interface Props {
    stats: {
        total_users: number;
        total_admins: number;
        total_cashiers: number;

        total_products: number;
        total_product_categories: number;

        total_orders: number;
        orders_need_attention: number;

        total_delivery_locations: number;
        total_delivery_areas: number;

        total_callbacks: number;
        total_unread_callbacks: number;

        monthly_sales: number[];
        payment_breakdown: {
            mpesa: number;
            cash: number;
        };
        total_revenue: number;
        total_cogs: number;
        total_gross_profit: number;
        gross_profit_margin: number;
        aov: number;
    }
};

const props = defineProps<Props>();

const lineChartData = computed(() => ({
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    datasets: [
        {
            label: 'Sales (Ksh)',
            data: props.stats.monthly_sales,
            borderColor: '#3b82f6', // blue-500
            backgroundColor: 'rgba(59, 130, 246, 0.1)', // fill color (light blue)
            borderWidth: 3,
            fill: true,
        }
    ]
}));

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false
        },
        tooltip: {
            callbacks: {
                label: (context: any) => `Ksh ${context.parsed.y.toLocaleString()}`
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { callback: (value: any) => `${value.toLocaleString()}` }
        }
    }
};

const pieChartData = computed(() => ({
    labels: ['M-Pesa', 'Cash'],
    datasets: [
        {
            data: [
                props.stats.payment_breakdown.mpesa, 
                props.stats.payment_breakdown.cash
            ],
            backgroundColor: ['#10b981', '#f59e0b'], // green-500, amber-500
            borderWidth: 1
        }
    ]
}));

const pieChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            position: 'right' as const, 
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const value = context.parsed;
                    const dataset = context.dataset;
                    const total = dataset.data.reduce((a: number, b: number) => a + b, 0);

                    if (total === 0) {
                        return `${context.label}: Ksh 0 (0%)`;
                    }

                    const percentage = ((value / total) * 100).toFixed(1);

                    return `Ksh ${value.toLocaleString()} (${percentage}%)`;
                }
            }
        }
    }
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="Dashboard AdminDashboard space-y-12">
        <section class="header">
            <div class="flex items-center gap-4">
                <p>Hi {{ user.name }}</p>
                <span class="text-xs text-blue-900 bg-blue-100 py-1 px-2 rounded-sm">{{ user.role_label }}</span>
            </div>
        </section>

        <section class="stats-wrapper">
            <h2 class="mb-4 font-medium">Platform Statistics</h2>
            
            <div class="stats grid gap-8 lg:grid-cols-6">
                <DashboardStat :stat="stats.total_users" label="Users">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.total_admins }} Admins & {{ stats.total_cashiers }} Cashiers
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.total_orders" label="Orders">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.orders_need_attention }} need attention
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.total_products" label="Products">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            {{ stats.total_product_categories }} Categories
                        </span>
                    </template>
                </DashboardStat>
            </div>
        </section>

        <section class="financial-stats-wrapper">
            <h2 class="mb-4 font-medium">Fiscal Overview</h2>

            <div class="stats grid gap-8 lg:grid-cols-5">
                <DashboardStat :stat="stats.total_revenue" label="Total Revenue">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            All sales at full price
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.total_cogs" label="Total COGS" variant="danger">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            Total Cost of Goods
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.total_gross_profit" label="Gross Profit" variant="success">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            All sales minus cost of goods
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.gross_profit_margin" format="percent" label="Gross Profit Margin">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            Percentage of revenue kept after COGS
                        </span>
                    </template>
                </DashboardStat>

                <DashboardStat :stat="stats.aov" format="currency" label="AOV / ATV">
                    <template #extras>
                        <span class="text-sm text-muted-foreground">
                            Average Order Value
                        </span>
                    </template>
                </DashboardStat>
            </div>
        </section>

        <section class="charts-wrapper grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Line Chart (Spans 2 columns) -->
            <div class="chart-card bg-background p-4 rounded-lg border border-border lg:col-span-2 h-80">
                <h3 class="text-sm font-medium mb-2">Sales Performance in Ksh. ({{ new Date().getFullYear() }})</h3>
                <div class="h-65">
                    <Line :data="lineChartData" :options="lineChartOptions" style="height: 100%!important; width: 100%!important;" />
                </div>
            </div>

            <!-- Pie Chart (Spans 1 column) -->
            <div class="chart-card bg-background p-4 rounded-lg border border-border h-80">
                <h3 class="text-sm font-medium mb-2">Payment Methods</h3>
                <div class="h-65">
                    <Pie :data="pieChartData" :options="pieChartOptions" style="height: 100%!important; width:100%!important" />
                </div>
            </div>
        </section>
    </div>
</template>