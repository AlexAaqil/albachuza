<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import FormHeader from '@/components/custom/FormHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import expenseRoutes from '@/routes/expenses';

defineOptions({
    layout: {
        title: 'Create Expense',
        description: 'Add a new expense',
    },
});

interface Category {
    id: number;
    name: string;
}

defineProps<{
    categories: Category[];
}>();
</script>

<template>
    <Head title="Create Expense" />

    <div class="form">
        <FormHeader :backUrl="expenseRoutes.index().url" title="Create Expense" />

        <Form :action="expenseRoutes.store.url()" method="post" v-slot="{ errors, processing }">
            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="amount" class="required">Amount</Label>
                    <Input
                        id="amount"
                        type="number"
                        autofocus
                        autocomplete="amount"
                        name="amount"
                        placeholder="5000"
                    />
                    <InputError :message="errors.amount" />
                </div>

                <div class="inputs-group">
                    <Label for="expense_category_id">Expense Category</Label>
                    <Select name="expense_category_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select expense category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="null">None</SelectItem>
                                <SelectItem 
                                    v-for="option in categories" 
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ option.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.expense_category_id" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="expense_date" class="required">Date</Label>
                    <Input
                        id="expense_date"
                        type="date"
                        autocomplete="expense_date"
                        name="expense_date"
                    />
                    <InputError :message="errors.expense_date" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="payment_method">Payment Method</Label>
                    <Input
                        id="payment_method"
                        type="text"
                        autocomplete="payment_method"
                        name="payment_method"
                        placeholder="Mpesa, PayPal, Bank Transfer"
                    />
                    <InputError :message="errors.payment_method" />
                </div>

                <div class="inputs-group">
                    <Label for="receipt_number">Receipt Number</Label>
                    <Input
                        id="receipt_number"
                        type="text"
                        autocomplete="receipt_number"
                        name="receipt_number"
                        placeholder="XMY45345KKKD"
                    />
                    <InputError :message="errors.receipt_number" />
                </div>
            </div>

            <div class="inputs-group">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="Describe your product category..."
                />
                <InputError :message="errors.description" />
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create Expense
                </Button>

                <div>
                    <Link :href="expenseRoutes.index().url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </div>
        </Form>
    </div>
</template>