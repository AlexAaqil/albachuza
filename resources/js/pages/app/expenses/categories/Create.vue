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
        title: 'Create Expense Category',
        description: 'Add a new expense category',
    },
});
</script>

<template>
    <Head title="Create Expense Category" />

    <div class="form">
        <FormHeader :backUrl="expenseCategoryRoutes.index().url" title="Create Expense Category" />

        <Form :action="expenseCategoryRoutes.store.url()" method="post" v-slot="{ errors, processing }">
            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="name" class="required">Category Name</Label>
                    <Input
                        id="name"
                        type="text"
                        autofocus
                        autocomplete="name"
                        name="name"
                        placeholder="Category name"
                    />
                    <InputError :message="errors.name" />
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
                    Create Category
                </Button>

                <div>
                    <Link :href="expenseCategoryRoutes.index().url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </div>
        </Form>
    </div>
</template>