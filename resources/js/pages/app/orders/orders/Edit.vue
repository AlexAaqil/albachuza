<script setup lang="ts">
import { Link, Head, useForm } from '@inertiajs/vue3';
import FormHeader from '@/components/custom/FormHeader.vue';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import orderRoutes from '@/routes/orders';

const {formatPrice} = usePriceFormatter();

interface OrderItem {
    id: number;
    product_name: string;
    quantity: number;
    selling_price: number;
}

interface Payment {
    id: number;
    payment_method: string;
    payment_status: string;
    amount: number;
}

interface Order {
    id: number;
    uuid: string;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string;
    delivery_location: string;
    delivery_area: string;
    delivery_address: string;
    notes: string;
    payment_method: string;
    payment_status: string;
    order_status: string;
    order_status_label: string;
    delivery_status: string;
    delivery_status_label: string;
    sold_at: string;
    order_items: OrderItem[];
    payments: Payment[];
    delivery_cost: number;
    subtotal: number;
    total_selling_price: number;
    amount_paid: number;
    balance: number;
    is_fully_paid: boolean;
    balance_label: string;
    discount: number;
    discount_reason: string;
}

const props = defineProps<{
    order: {
        data: Order
    };
    orderStatuses: Record<string, string>;
    deliveryStatuses: Record<string, string>;
}>();

const form = useForm({
    order_status: props.order.data.order_status,
    delivery_status: props.order.data.delivery_status,
    notes: props.order.data.notes || '',
    _method: 'PUT',
});

const submitForm = () => {
    form.put(orderRoutes.update.url(props.order.data.uuid), {
        preserveScroll: true
    });
};

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    reference: '',
    notes: '',
});

const submitPayment = () => {
    paymentForm.post(`/orders/${props.order.data.uuid}/payments`, {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset('amount', 'reference', 'notes'),
    });
};

// Convenience: pre-fill the amount with the outstanding balance
const fillBalance = () => {
    paymentForm.amount = String(props.order.data.balance);
};

const discountForm = useForm({
    discount: '',
    reason: '',
});

const submitDiscount = () => {
    discountForm.post(`/orders/${props.order.data.uuid}/discount`, {
        preserveScroll: true,
        onSuccess: () => discountForm.reset(),
    });
};
</script>

<template>
    <Head title="Edit Order" />

    <div class="order_details grid lg:grid-cols-2 gap-8">
        <div class="details-section">
            <p class="font-bold text-subheading-text">Order Details</p>
            <p class="flex">
                <span class="text-muted-foreground w-30">Order Number</span>
                <span>: {{ order.data.order_number }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Name</span>
                <span>: {{ order.data.customer_name }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Phone</span>
                <span>: {{ order.data.customer_phone }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Email</span>
                <span>: {{ order.data.customer_email ?? 'na' }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Location</span>
                <span>: {{ order.data.delivery_location }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Location</span>
                <span>: {{ order.data.delivery_area }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Address</span>
                <span>: {{ order.data.delivery_address }}</span>
            </p>

            <p class="flex">
                <span class="text-muted-foreground w-30">Date</span>
                <span>: {{ order.data.sold_at }}</span>
            </p>

            <!-- Current Status Display -->
            <div class="mt-4 p-3 bg-gray-50 rounded border">
                <p class="flex">
                    <span class="text-muted-foreground w-30">Current Order Status</span>
                    <span class="font-medium">: {{ order.data.order_status_label }}</span>
                </p>
                <p class="flex" v-if="order.data.delivery_status">
                    <span class="text-muted-foreground w-30">Current Delivery Status</span>
                    <span class="font-medium">: {{ order.data.delivery_status_label }}</span>
                </p>
            </div>
        </div>

        <div class="extras">
            <div class="order-items">
                <p class="font-bold text-subheading-text">Items Ordered</p>
                <div v-for="item in order.data.order_items" :key="item.id" class="items mb-4">
                    <div class="item flex gap-4">
                        <span>{{ item.product_name }}</span>
                        <span>{{ item.quantity }} @ {{ formatPrice(item.selling_price) }}</span>
                        <span>= Ksh. {{ formatPrice(item.quantity * item.selling_price) }}</span>
                    </div>
                </div>
                <p class="flex">
                    <span class="text-muted-foreground w-30">Items Total</span>
                    <span>: {{ formatPrice(order.data.subtotal) }}</span>
                </p>
                <p class="flex">
                    <span class="text-muted-foreground w-30">Discount</span>
                    <span>: {{ formatPrice(order.data.discount) }} ({{ order.data.discount_reason }})</span>
                </p>
                <p class="flex">
                    <span class="text-muted-foreground w-30">Shipping</span>
                    <span>: {{ formatPrice(order.data.delivery_cost) }}</span>
                </p>
                <p class="flex">
                    <span class="text-muted-foreground w-30">Total</span>
                    <span>: {{ formatPrice(order.data.total_selling_price) }}</span>
                </p>
            </div>

            <div class="payment-info mt-12">
                <p class="font-bold text-subheading-text">Payments Summary</p>
                <p class="flex">
                    <span class="text-muted-foreground w-32">Amount Paid</span>
                    <span>: {{ formatPrice(order.data.amount_paid) }}</span>
                </p>

                <p v-if="!order.data.is_fully_paid" class="flex">
                    <span class="text-muted-foreground w-32">Balance Due</span>
                    <span class="text-red-600 font-medium">: {{ formatPrice(order.data.balance) }}</span>
                </p>

                <p class="flex">
                    <span class="text-muted-foreground w-32">Payment Status</span>
                    <span>: {{ order.data.payment_status }}</span>
                </p>

                <p class="mt-4">Payments Made</p>
                <p v-for="payment in order.data.payments" :key="payment.id" class="flex">
                    <span class="text-muted-foreground w-32">{{ payment.payment_method }}</span>
                    <span>: {{ formatPrice(payment.amount) }}</span>
                </p>
            </div>
        </div>
    </div>

    <div v-if="!order.data.is_fully_paid" class="discount-form mt-6 p-4 border rounded">
        <p class="font-bold text-subheading-text mb-2">Apply Discount</p>
        <div class="flex gap-2 items-end">
            <div class="flex-1">
                <Label for="discount">Discount amount</Label>
                <Input v-model="discountForm.discount" type="number" step="0.01" min="0" />
                <InputError :message="discountForm.errors.discount" />
            </div>
            <div class="flex-1">
                <Label for="reason">Reason (optional)</Label>
                <Input v-model="discountForm.reason" />
            </div>
            <Button type="button" :disabled="discountForm.processing" @click="submitDiscount">
                Apply
            </Button>
        </div>
        <p class="text-xs text-muted-foreground mt-2">
            Paid so far: {{ formatPrice(order.data.amount_paid) }}.
            New total will be recalculated.
        </p>
    </div>

    <div v-if="!order.data.is_fully_paid" class="payment-form mt-6 p-4 border rounded">
        <p class="font-bold text-subheading-text mb-2">
            Record Payment
            <span class="text-sm font-normal text-muted-foreground">
                ({{ formatPrice(order.data.balance) }} outstanding)
            </span>
        </p>

        <div class="flex gap-2 items-end">
            <div class="flex-1">
                <Label for="amount">Amount</Label>
                <Input v-model="paymentForm.amount" type="number" step="0.01" min="0.01" class="w-full rounded-sm border border-gray-400 px-2 py-1" />
                <InputError :message="paymentForm.errors.amount" />
            </div>

            <div class="flex-1">
                <Label for="method">Method</Label>
                <Select v-model="paymentForm.payment_method" class="w-full rounded-sm border border-gray-400 px-2 py-1">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="mpesa">M-Pesa</SelectItem>
                        <SelectItem value="card">Card</SelectItem>
                        <SelectItem value="bank">Bank</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="paymentForm.errors.payment_method" />
            </div>

            <Button type="button" variant="outline" @click="fillBalance">
                Pay full balance
            </Button>

            <Button type="button" :disabled="paymentForm.processing" @click="submitPayment">
                <Spinner v-if="paymentForm.processing" />
                Record
            </Button>
        </div>
    </div>

    <div v-else class="mt-6 p-3 bg-green-50 border border-green-200 rounded text-green-800 text-sm">
        ✓ This order is fully paid.
    </div>

    <div class="form min-w-full px-0.5">
        <FormHeader :backUrl="orderRoutes.index().url" title="Edit Order" />

        <form @submit.prevent="submitForm">
            <input type="hidden" name="_method" value="PUT" />

            <div class="inputs-group">
                <Label for="order_status">Order Status</Label>
                <Select v-model="form.order_status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select order status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem 
                                v-for="(label, value) in orderStatuses" 
                                :key="value" 
                                :value="value"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.order_status" />
            </div>
            
            <div class="inputs-group">
                <Label for="delivery_status">Delivery Status</Label>
                <Select v-model="form.delivery_status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select delivery status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem 
                                v-for="(label, value) in deliveryStatuses" 
                                :key="value" 
                                :value="value"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.delivery_status" />
            </div>

            <div class="inputs-group">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    placeholder="Describe your notes..."
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Update Order
                </Button>

                <div>
                    <Link :href="orderRoutes.index().url">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </div>
        </form>
    </div>
</template>