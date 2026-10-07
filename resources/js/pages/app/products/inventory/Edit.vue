<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import Button from '@/components/ui/button/Button.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import productInventoryRoutes from '@/routes/products-inventory';

const props = defineProps<{
    product: { id: number; uuid: string; name: string; sku: string; current_stock: number };
    movement_types: {
        add: Record<number, string>;
        remove: Record<number, string>;
        all: Record<number, { label: string; operation: string }>;
    };
}>();

const form = useForm({
    quantity: 1,
    type: null as number | null,
    notes: ''
});

// For Edit page - only show remove operations
const movementOptions = computed(() => {
    return Object.entries(props.movement_types.remove).map(([value, label]) => ({
        value: parseInt(value),
        label: label
    }));
});

const newStockPreview = computed(() => {
    if (!form.quantity) return props.product.current_stock;
    return Math.max(0, props.product.current_stock - form.quantity);
});

const submit = () => {
    form.put(productInventoryRoutes.update.url(props.product.uuid), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Remove Stock - ${product.name}`" />

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-6">
            <Link :href="productInventoryRoutes.index()" class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to Inventory
            </Link>
            <h2 class="text-2xl font-bold mt-2">Remove Stock: {{ product.name }}</h2>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">SKU</p>
                        <p class="font-medium">{{ product.sku || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Current Stock</p>
                        <p class="font-medium">{{ product.current_stock }} units</p>
                    </div>
                </div>
            </div>

            <div class="inputs-group">
                <Label for="type" class="required">Movement Type</Label>
                <select
                    id="type"
                    v-model.number="form.type"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900"
                    required
                >
                    <option :value="null">Select Movement Type</option>
                    <option v-for="movement in movementOptions" :key="movement.value" :value="movement.value">{{ movement.label }}</option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Quantity to Remove *</label>
                <Input 
                    v-model.number="form.quantity" 
                    type="number" 
                    min="1"
                    :max="product.current_stock"
                    required 
                />
                <p class="text-xs text-gray-500 mt-1">
                    New stock will be: 
                    <span class="font-medium" :class="{
                        'text-red-600': newStockPreview < product.current_stock
                    }">
                        {{ newStockPreview }} units
                    </span>
                </p>
                <p v-if="form.quantity > product.current_stock" class="text-xs text-red-600 mt-1">
                    Cannot remove more than current stock ({{ product.current_stock }} units available)
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Notes (Optional)</label>
                <Textarea 
                    v-model="form.notes" 
                    rows="3"
                    placeholder="e.g., Damaged items returned to supplier, Sold in bulk" 
                />
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-sm text-red-800">
                    This will create an inventory movement record and decrease your available stock.
                    All changes are tracked for audit purposes.
                </p>
            </div>

            <div class="flex gap-3 justify-end pt-4">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Processing...' : 'Remove Stock' }}
                </Button>

                <Link :href="productInventoryRoutes.index()">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
            </div>
        </form>
    </div>
</template>