<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import FormHeader from '@/components/custom/FormHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import expenseCategoryRoutes from '@/routes/expense-categories';

defineOptions({
    layout: {
        title: 'Edit Expense Category',
        description: 'Edit expense category',
    },
});

interface Props {
    category: {
        id: number;
        uuid: string;
        name: string;
        description: string;
    };
}

const props = defineProps<Props>();
const expenseCategory = props.category;
</script>

<template>
    <Head title="Edit Expense Category" />

    <div class="form">
        <FormHeader :backUrl="expenseCategoryRoutes.index().url" title="Edit Expense Category" />

        <Form :action="expenseCategoryRoutes.update(expenseCategory.uuid).url" method="put" v-slot="{ errors, processing }">
            <div class="inputs-group">
                <Label for="name" class="required">Name</Label>
                <Input
                    id="name"
                    type="text"
                    autofocus
                    autocomplete="name"
                    name="name"
                    :default-value="expenseCategory.name"
                    placeholder="Expense Category Name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="inputs-group">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    name="description"
                    rows="4"
                    :default-value="expenseCategory.description"
                    placeholder="Describe your product category..."
                />
                <InputError :message="errors.description" />
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Update Category
                </Button>

                <Link :href="expenseCategoryRoutes.index().url">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
            </div>
        </Form>
    </div>
</template>
