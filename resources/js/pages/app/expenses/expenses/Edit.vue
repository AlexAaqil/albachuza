<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
        title: 'Edit Expense',
        description: 'Edit expense record',
    },
});

interface Category {
    id: number;
    name: string;
}

interface Expense {
    id: number;
    uuid: string;
    amount: string | number;
    expense_date: string;
    payment_method: string | null;
    receipt_number: string | null;
    expense_category_id: number | null;
    description: string | null;
}

const props = defineProps<{
    expense: {
        data: Expense;
    };
    categories: Category[];
}>();

const expense = props.expense;

const form = useForm({
    amount: expense.data.amount,
    expense_date: expense.data.expense_date,
    payment_method: expense.data.payment_method || '',
    receipt_number: expense.data.receipt_number || '',
    expense_category_id: expense.data.expense_category_id,
    description: expense.data.description || '',
    _method: 'PUT',
});

const submitForm = () => {
    form.put(expenseRoutes.update(expense.data.uuid).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Edit Expense" />

    <div class="form">
        <FormHeader :backUrl="expenseRoutes.index().url" title="Edit Expense" />

        <form @submit.prevent="submitForm">
            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="amount" class="required">Amount</Label>
                    <Input
                        id="amount"
                        type="number"
                        step="0.01"
                        autofocus
                        v-model="form.amount"
                        placeholder="5000"
                    />
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="inputs-group">
                    <Label for="expense_category_id">Expense Category</Label>
                    <Select v-model="form.expense_category_id">
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
                    <InputError :message="form.errors.expense_category_id" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="expense_date" class="required">Date</Label>
                    <Input
                        id="expense_date"
                        type="date"
                        v-model="form.expense_date"
                    />
                    <InputError :message="form.errors.expense_date" />
                </div>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="payment_method">Payment Method</Label>
                    <Input
                        id="payment_method"
                        type="text"
                        v-model="form.payment_method"
                        placeholder="Mpesa, PayPal, Bank Transfer"
                    />
                    <InputError :message="form.errors.payment_method" />
                </div>

                <div class="inputs-group">
                    <Label for="receipt_number">Receipt Number</Label>
                    <Input
                        id="receipt_number"
                        type="text"
                        v-model="form.receipt_number"
                        placeholder="XMY45345KKKD"
                    />
                    <InputError :message="form.errors.receipt_number" />
                </div>
            </div>

            <div class="inputs-group">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    placeholder="Describe the expense..."
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Update Expense
                </Button>

                <div>
                    <Link :href="expenseRoutes.index().url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </div>
        </form>
    </div>
</template>