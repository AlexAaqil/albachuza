<script setup lang="ts">
import { useForm, Head, router } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Search } from '@lucide/vue';
import axios from 'axios';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import orderRoutes from '@/routes/orders';
import type { Product } from '@/types/product';

interface RecentOrder {
    id: number;
    uuid: string;
    order_number: string;
    customer_name: string;
    total_selling_price: number;
    order_status: string;
    order_status_label: string;
    user?: { id: number; name: string } | null;
    created_at: string;
}

interface CartItemPayload extends Product {
    quantity: number;
}

const { formatPrice } = usePriceFormatter();

const props = defineProps<{
    products: { data: Product[] };
    recent_orders: { data: RecentOrder[] };
    can_view_all: boolean;
}>();

const statusClasses: Record<string, string> = {
    pending:         'bg-yellow-100 text-yellow-800',
    confirmed:       'bg-blue-100 text-blue-800',
    processing:      'bg-blue-100 text-blue-800',
    ready_for_pickup:'bg-purple-100 text-purple-800',
    completed:       'bg-green-100 text-green-800',
    cancelled:       'bg-red-100 text-red-800',
};

const statusClass = (status: string) => statusClasses[status] ?? 'bg-gray-100 text-gray-800';

// --- STATE ---
const cart = ref<CartItemPayload[]>([]);

// --- INERTIA FORM ---
const form = useForm({
    user_id: null as number | null,
    customer_name: 'Walk-in',
    customer_phone: '',
    customer_email: '',
    delivery_method: 'shop',
    order_channel: 'pos',
    location: 'shop',
    area: 'shop',
    address: 'shop',
    delivery_cost: 0,
    amount_paid: 0,
    payments: [{ amount: 0, method: 'mpesa' }],
    cart_items: [] as CartItemPayload[], 
});

// --- COMPUTED TOTALS ---
const subtotal = computed(() => cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0));

const totalItemsCount = computed(() => cart.value.reduce((sum, item) => sum + item.quantity, 0));

const total = computed(() => subtotal.value);

// --- CUSTOMER LOOKUP BY PHONE ---
const lookupLoading = ref(false);
const lookupError   = ref<string | null>(null);
let lookupDebounce: number | undefined;

const lookupCustomer = (phone: string) => {
    window.clearTimeout(lookupDebounce);

    if (!phone || phone.length < 7) {
        // Reset to walk-in defaults
        form.user_id = null;
        form.customer_name = 'Walk-in';
        form.customer_email = '';
        lookupError.value = null;
        return;
    }

    lookupDebounce = window.setTimeout(async () => {
        lookupLoading.value = true;
        lookupError.value = null;
        try {
            const { data } = await axios.get('/customers/lookup', {
                params: { phone },
            });

            if (data?.name) {
                form.user_id = data.id;
                form.customer_name  = data.name;
                form.customer_email = data.email ?? '';
            } else {
                form.user_id = null;
                form.customer_name  = 'Walk-in';
                form.customer_email = '';
                lookupError.value = 'No account found — will be saved as walk-in.';
            }
        } catch (e) {
            form.customer_name  = 'Walk-in';
            form.customer_email = '';
            lookupError.value = 'Lookup failed — will be saved as walk-in.';
        } finally {
            lookupLoading.value = false;
        }
    }, 350);
};
// Watch the phone field and trigger lookup
watch(() => form.customer_phone, (val) => lookupCustomer(val));

// --- ACTIONS ---
const addToCart = (product: Product) => {
    // Check if already in cart
    const existing = cart.value.find(item => item.id === product.id);

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({ ...product, quantity: 1 });
    }
};

const removeFromCart = (id: number) => {
    cart.value = cart.value.filter(item => item.id !== id);
};

const increaseQuantity = (id: number) => {
    const item = cart.value.find(item => item.id === id);
    if (item) item.quantity += 1;
};

const decreaseQuantity = (id: number) => {
    const item = cart.value.find(item => item.id === id);
    if (item) {
        if (item.quantity > 1) {
            item.quantity -= 1;
        } else {
            removeFromCart(id);
        }
    }
};

const payments = ref([
    { amount: 0, method: 'mpesa'}
]);

const addPaymentRow = () => {
    payments.value.push({amount: 0, method: 'mpesa'});
}

const removePaymentRow = (index: number) => {
    payments.value.splice(index, 1);
}

const submitOrder = () => {
    // Populate the cart_items in the form before submitting
    // Send cart items with quantity
    form.cart_items = cart.value.map(item => ({
        ...item,
        quantity: item.quantity,
    }));
    form.payments = payments.value;

    form.post(orderRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            // Reset state after success
            cart.value = [];
            payments.value = [{amount: 0, method: 'mpesa'}];
            form.amount_paid = payments.value.reduce((s, p) => s + Number(p.amount || 0), 0);
            form.reset(); 
            form.customer_name = 'Walk-in';
            form.customer_phone = '';
            form.customer_email = '';
            form.user_id = null;

            router.reload({only: ['recent_orders', 'products']})
        }
    });
};

let interval: number | undefined;

onMounted(() => {
    if (!props.can_view_all) return; // only admins poll
    interval = window.setInterval(() => {
        router.reload({ only: ['recent_orders'] });
    }, 30_000); // every 30s
});

onUnmounted(() => clearInterval(interval));

// --- PRODUCT SEARCH ---
const searchQuery = ref('');

const searchInput = ref<HTMLInputElement | null>(null);

onMounted(() => {
    // Existing code...
    nextTick(() => searchInput.value?.focus());
});

const filteredProducts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    
    if (!query) {
        return props.products.data;
    }

    return props.products.data.filter((product) => {
        const name = product.name?.toLowerCase() ?? '';
        const barcode = product.barcode?.toLowerCase() ?? '';
        const sku = (product as any).sku?.toLowerCase() ?? '';
        const category = (product as any).category?.name?.toLowerCase() ?? '';
        
        return name.includes(query) 
            || sku.includes(query) 
            || barcode.includes(query)
            || category.includes(query);
    });
});

const handleKeydown = (e: KeyboardEvent) => {
    // Don't hijack if already typing in an input
    const target = e.target as HTMLElement;
    const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) 
        || target.isContentEditable;

    if (e.key === '/' && !isTyping) {
        e.preventDefault();
        searchInput.value?.focus();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.clearTimeout(lookupDebounce);
    window.removeEventListener('keydown', handleKeydown);
    clearInterval(interval);
});
</script>

<template>
    <Head title="POS - New Order" />

    <div class="pos-wrapper grid lg:grid-cols-2 gap-8 h-[84dvh] p-4 bg-background text-foreground overflow-hidden">
        <div class="products-wrapper overflow-y-auto pr-4">
            <div class="flex items-center justify-between mb-4 gap-3">
                <h2 class="text-xl font-bold">Select Products</h2>
                <span class="text-xs text-gray-500">
                    {{ filteredProducts.length }} 
                    {{ filteredProducts.length === 1 ? 'product' : 'products' }}
                </span>
            </div>

            <!-- Search Input -->
            <div class="relative mb-4">
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by name, SKU, or category... (press / to focus)"
                    class="pl-9"
                    @keydown.esc="searchQuery = ''"
                />
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">
                    <Search class="w-4 h-4" />
                </span>
                <button
                    v-if="searchQuery"
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm"
                    @click="searchQuery = ''"
                    aria-label="Clear search"
                >
                    ✕
                </button>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                    v-for="product in filteredProducts" 
                    :key="product.id" 
                    class="bg-background text-foreground p-3 rounded-lg shadow-sm border hover:shadow-md transition"
                >
                    <div class="w-full h-32 overflow-hidden rounded mb-2 bg-gray-100">
                        <img :src="product.thumbnail_url" :alt="product.name" class="w-full h-full object-cover" />
                    </div>

                    <div class="space-y-1">
                        <p class="font-semibold text-sm truncate">{{ product.name }}</p>
                        <p class="text-blue-600 font-bold text-sm">{{ formatPrice(product.price) }}</p>
                        <Button 
                            type="button" 
                            size="sm"
                            class="w-full bg-blue-800 hover:bg-blue-900 text-white"
                            @click="addToCart(product)"
                        >
                            {{ cart.find(i => i.id === product.id) ? 'Add Another' : 'Add to Cart' }}
                            <span 
                                v-if="cart.find(i => i.id === product.id)?.quantity"
                                class="text-white text-xs"
                            >
                                ( {{ cart.find(i => i.id === product.id)!.quantity }} )
                            </span>
                        </Button>
                    </div>
                </div>

                <!-- Empty state: no products at all -->
                <div 
                    v-if="!products.data || products.data.length === 0" 
                    class="col-span-full text-center py-12"
                >
                    <div class="text-gray-400 text-6xl mb-4">📦</div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No products available!</h3>
                    <p class="text-gray-400">There are no stocked products!</p>
                </div>

                <!-- Empty state: search returned nothing -->
                <div 
                    v-else-if="filteredProducts.length === 0" 
                    class="col-span-full text-center py-12"
                >
                    <div class="text-gray-400 text-6xl mb-4">🔍</div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No matches found</h3>
                    <p class="text-gray-400 mb-4">
                        No products match "<span class="font-medium">{{ searchQuery }}</span>"
                    </p>
                    <Button type="button" variant="outline" size="sm" @click="searchQuery = ''">
                        Clear search
                    </Button>
                </div>
            </div>
        </div>

        <div class="cart-wrapper bg-background text-foreground p-6 rounded-lg shadow-lg h-full overflow-y-auto flex flex-col">
            <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center justify-between">
                <span>Current Order</span>
                <span 
                    v-if="totalItemsCount > 0" 
                    class="text-sm bg-blue-600 text-white rounded-full px-2 py-0.5"
                >
                    {{ totalItemsCount }} {{ totalItemsCount === 1 ? 'item' : 'items' }}
                </span>
            </h2>
            
            <!-- Cart Items List -->
            <div class="cart-items space-y-2 min-h-25 max-h-50 overflow-y-auto mb-4 flex-1" v-if="cart.length > 0">
                <div v-for="item in cart" :key="item.id" class="flex justify-between items-center text-sm bg-background text-foreground p-2 rounded border">
                    <div class="flex-1 min-w-0">
                        <span class="font-medium truncate block">{{ item.name }}</span>
                        <span class="text-gray-500 text-xs">{{ formatPrice(item.price) }} each</span>
                    </div>

                    <!-- Quantity Controls -->
                    <div class="flex items-center gap-2">
                        <Button 
                            type="button" 
                            variant="outline" 
                            size="icon" 
                            class="h-6 w-6 rounded-full text-xs"
                            @click="decreaseQuantity(item.id)"
                        >
                            −
                        </Button>
                        <span class="w-6 text-center font-semibold">{{ item.quantity }}</span>
                        <Button 
                            type="button" 
                            variant="outline" 
                            size="icon" 
                            class="h-6 w-6 rounded-full text-xs"
                            @click="increaseQuantity(item.id)"
                        >
                            +
                        </Button>
                    </div>

                    <div class="flex items-center gap-3 ml-3">
                        <span class="font-bold w-20 text-right">{{ formatPrice(item.price * item.quantity) }}</span>
                        <Button 
                            type="button" 
                            variant="destructive" 
                            size="icon" 
                            class="h-6 w-6 rounded-full text-xs"
                            @click="removeFromCart(item.id)"
                        >
                            ✕
                        </Button>
                    </div>
                </div>
            </div>

            <div v-else class="text-gray-400 text-center py-6 flex-1">No items in cart</div>

            <!-- Order Form -->
            <form @submit.prevent="submitOrder" class="space-y-4 border-t pt-4">
                <!-- Customer Phone (lookup) -->
                <div class="space-y-3">
                    <h3 class="font-semibold text-sm text-gray-700">Customer</h3>
                    <div>
                        <Label for="customer_phone" class="text-xs">Phone Number</Label>
                        <div class="relative">
                            <Input
                                id="customer_phone"
                                v-model="form.customer_phone"
                                placeholder="e.g. 0712345678 (leave blank for walk-in)"
                            />
                            <Spinner
                                v-if="lookupLoading"
                                class="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4"
                            />
                        </div>
                        <p v-if="lookupError" class="text-[11px] text-amber-600 mt-1">
                            {{ lookupError }}
                        </p>
                        <p v-else-if="form.user_id" class="text-[11px] text-green-600 mt-1">
                            Matched customer: {{ form.customer_name }}
                        </p>
                        <InputError :message="form.errors.customer_phone" />
                    </div>
                </div>

                <!-- Dynamic Payments -->
                <div class="space-y-2 mt-2">
                    <h3 class="font-semibold text-sm text-gray-700">Payments</h3>
                    <div v-for="(payment, index) in payments" :key="index" class="flex gap-2">
                        <Input
                            v-model="payment.amount"
                            type="number"
                            step="0.01"
                            placeholder="Amount"
                            class="w-1/2"
                        />
                        <Select v-model="payment.method">
                            <SelectTrigger class="w-1/2">
                                <SelectValue placeholder="Method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="mpesa">M-Pesa</SelectItem>
                                    <SelectItem value="cash">Cash</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="destructive"
                            size="icon"
                            class="h-9 w-9"
                            @click="removePaymentRow(index)"
                            v-if="payments.length > 1"
                        >
                            ✕
                        </Button>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="addPaymentRow">
                        + Add Payment Method
                    </Button>
                </div>

                <!-- Summary -->
                <div class="bg-background text-foreground p-3 rounded space-y-1">
                    <div class="flex justify-between text-sm">
                        <span>Subtotal</span><span>{{ formatPrice(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-base border-t pt-1 mt-1">
                        <span>Total</span><span>{{ formatPrice(total) }}</span>
                    </div>
                    <div class="mt-2 pt-2 border-t text-xs text-gray-500">
                        <p>Order: <span class="font-medium">Ready for Pickup</span></p>
                        <p>Delivery: <span class="font-medium">Picked Up</span></p>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 mt-2"
                    :disabled="form.processing || cart.length === 0 || lookupLoading"
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    Confirm Order
                </Button>
            </form>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="recent-orders border-t pt-4 mt-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-sm text-gray-700">
                {{ can_view_all ? 'Recent Orders (All Users)' : 'Your Recent Orders' }}
            </h3>

            <a
                v-if="can_view_all"
                :href="orderRoutes.index().url"
                class="text-xs text-blue-600 hover:underline"
            >
                View all →
            </a>
        </div>

        <div v-if="recent_orders.data.length === 0" class="text-xs text-gray-400 py-4 text-center">
            No recent orders
        </div>

        <div v-else class="space-y-2 max-h-64 overflow-y-auto pr-1">
            <a
                v-for="order in recent_orders.data"
                :key="order.id"
                :href="orderRoutes.edit(order.uuid).url"
                class="flex items-center justify-between p-2 rounded border text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition"
            >
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-[10px] text-gray-500">
                            {{ order.order_number }}
                        </span>
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                            :class="statusClass(order.order_status)"
                        >
                            {{ order.order_status_label }}
                        </span>
                    </div>
                    <div class="text-gray-700 dark:text-gray-300 truncate">
                        {{ order.customer_name }}
                    </div>
                    <div v-if="can_view_all && order.user" class="text-[10px] text-gray-400">
                        by {{ order.user.name }}
                    </div>
                </div>
                <div class="text-right ml-3">
                    <div class="font-bold text-gray-900 dark:text-gray-100">
                        {{ formatPrice(order.total_selling_price) }}
                    </div>
                    <div class="text-[10px] text-gray-400">
                        {{ order.created_at }}
                    </div>
                </div>
            </a>
        </div>
    </div>
</template>