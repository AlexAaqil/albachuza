<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import InputError from '@/components/InputError.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import Select from '@/components/ui/select/Select.vue';
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

// For Create page - only show add operations
const movementOptions = computed(() => {
    return Object.entries(props.movement_types.add).map(([value, label]) => ({
        value: parseInt(value),
        label: label
    }));
});

const newStockPreview = computed(() => {
    const qty = Number(form.quantity) || 0;
    return (Number(props.product.current_stock) + qty).toFixed(2);
});

const submit = () => {
    form.post(productInventoryRoutes.store.url(props.product.uuid), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Inventory Movement - ${product.name}`" />

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-6">
            <Link :href="productInventoryRoutes.index()" class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to Inventory
            </Link>
            <h2 class="text-2xl font-bold mt-2">Inventory Movement: {{ product.name }}</h2>
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
                >
                    <option :value="null">Inventory Movement Type</option>
                    <option v-for="movement in movementOptions" :key="movement.value" :value="movement.value">{{ movement.label }}</option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Quantity to Add *</label>
                <Input 
                    v-model.number="form.quantity" 
                    type="number" 
                    min="1"
                    step="0.01"
                />
                <p class="text-xs text-gray-500 mt-1">
                    New stock will be: 
                    <span class="font-medium text-green-600">
                        {{ newStockPreview }} units
                    </span>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Notes (Optional)</label>
                <Textarea 
                    v-model="form.notes" 
                    rows="3"
                    placeholder="e.g., Restock from supplier XYZ, PO #12345" 
                />
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-800">
                    This will create an inventory movement record and update your stock level.
                    All changes are tracked for audit purposes.
                </p>
            </div>

            <div class="flex gap-3 justify-end pt-4">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Processing...' : 'Submit Movement' }}
                </Button>

                <Link :href="productInventoryRoutes.index()">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
            </div>
        </form>
    </div>
</template>