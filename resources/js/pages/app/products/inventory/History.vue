<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Pagination from '@/components/custom/Pagination.vue';
import productInventoryRoutes from '@/routes/products-inventory';

interface Movement {
    id: number;
    type: string;
    type_label: string;
    quantity: number;
    formatted_quantity: string;
    quantity_before: number;
    quantity_after: number;
    notes: string | null;
    created_at_formatted: string;
    user: { name: string } | null;
    performed_by: string;
    is_system: boolean;
}

const props = defineProps<{
    product: { id: number; name: string };
    movements: { data: Movement[]; meta: any };
}>();

const formatDate = (date: string) => {
    return new Date(date).toLocaleString();
};

const getTypeColor = (typeLabel: string) => {
    const colors = {
        initial: 'bg-gray-100 text-gray-800',
        restock: 'bg-green-100 text-green-800',
        sale: 'bg-blue-100 text-blue-800',
        return: 'bg-purple-100 text-purple-800',
        damage: 'bg-red-100 text-red-800',
        adjustment: 'bg-orange-100 text-orange-800'
    };
    return colors[typeLabel as keyof typeof colors] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head :title="`Stock History - ${product.name}`" />

    <div class="app-container">
        <div class="mb-6">
            <Link :href="productInventoryRoutes.index()" 
                  class="text-blue-600 hover:underline text-sm">
                ← Back to Inventory
            </Link>
            <h1 class="text-2xl font-bold mt-2">Stock History: {{ product.name }}</h1>
        </div>

        <div class="md:hidden space-y-3">
            <div
                v-for="(movement, index) in movements.data"
                :key="movement.id"
                class="border border-border rounded-lg p-4 bg-card shadow-sm"
            >
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            #{{ (movements.meta.current_page - 1) * movements.meta.per_page + index + 1 }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                    </div>
                </div>

                <div class="space-y-1.5 text-sm mb-3">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Date:</span>
                        <span class="font-medium text-right">{{ movement.created_at_formatted }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Type:</span>
                        <span class="font-medium text-right inline-block px-2 py-1 rounded-full text-xs" :class="getTypeColor(movement.type)">
                            {{ movement.type_label }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Quantity:</span>
                        <span class="font-medium text-right" :class="movement.quantity > 0 ? 'text-green-600' : 'text-red-600'">
                            {{ movement.formatted_quantity }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Quantity Before:</span>
                        <span class="font-medium text-right">{{ movement.quantity_before }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Quantity After:</span>
                        <span class="font-medium text-right">{{ movement.quantity_after }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Updated by:</span>
                        <span v-if="movement.is_system" class="text-gray-500 italic">System</span>
                        <span v-else>{{ movement.performed_by }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Notes:</span>
                        <span class="font-medium text-sm text-gray-500">{{ movement.notes || '-' }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="movements.data.length === 0"
                class="border border-border rounded-lg p-8 text-center text-muted-foreground"
            >
                No movements found.
            </div>
        </div>

        <div class="table-wrapper hidden md:block">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Quantity</TableHead>
                        <TableHead>Before</TableHead>
                        <TableHead>After</TableHead>
                        <TableHead>Performed By</TableHead>
                        <TableHead>Notes</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="movement in movements.data" :key="movement.id">
                        <TableCell class="whitespace-nowrap text-sm">
                            {{ movement.created_at_formatted }}
                        </TableCell>
                        <TableCell>
                            <span :class="getTypeColor(movement.type)" 
                                  class="inline-block px-2 py-1 rounded-full text-xs font-medium">
                                {{ movement.type_label }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span :class="movement.quantity > 0 ? 'text-green-600' : 'text-red-600'"
                                  class="font-medium">
                                {{ movement.formatted_quantity }}
                            </span>
                        </TableCell>
                        <TableCell>{{ movement.quantity_before }}</TableCell>
                        <TableCell class="font-medium">{{ movement.quantity_after }}</TableCell>
                        <TableCell class="text-sm">
                            <span v-if="movement.is_system" class="text-gray-500 italic">System</span>
                            <span v-else>{{ movement.performed_by }}</span>
                        </TableCell>
                        <TableCell class="text-sm text-gray-500">{{ movement.notes || '-' }}</TableCell>
                    </TableRow>

                    <TableRow v-if="movements.data.length === 0">
                        <TableCell colspan="7" class="text-center py-8 text-gray-500">
                            No stock movements found
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="mt-4">
            <Pagination :meta="movements.meta" />
        </div>
    </div>
</template>