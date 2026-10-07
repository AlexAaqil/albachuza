<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import FormHeader from '@/components/custom/FormHeader.vue';
import productCategoryRoutes from '@/routes/product-categories';

defineOptions({
    layout: {
        title: 'Edit Product Category',
        description: 'Edit product category',
    },
});

interface Props {
    product_category: {
        id: number;
        name: string;
    };
}

const props = defineProps<Props>();
const productCategory = props.product_category;
</script>

<template>
    <Head title="Edit Product Category" />

    <div class="form">
        <FormHeader :backUrl="productCategoryRoutes.index().url" title="Edit Product Category" />

        <Form :action="productCategoryRoutes.update(productCategory.id).url" method="put" v-slot="{ errors, processing }">
            <div class="inputs-group">
                <Label for="name" class="required">Name</Label>
                <Input
                    id="name"
                    type="text"
                    autofocus
                    autocomplete="name"
                    name="name"
                    :default-value="productCategory.name"
                    placeholder="Product Category Name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Update Category
                </Button>

                <Link :href="productCategoryRoutes.index().url">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
            </div>
        </Form>
    </div>
</template>
