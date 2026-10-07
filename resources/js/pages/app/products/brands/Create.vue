<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import FormHeader from '@/components/custom/FormHeader.vue';
import productCategoryRoutes from '@/routes/product-categories';

defineOptions({
    layout: {
        title: 'Create Product Category',
        description: 'Add a new product category',
    },
});

const categoryPreview = ref<string | null>(null);
const categoryImage = ref<File | null>(null);

const handleCategoryImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        categoryImage.value = file;
        categoryPreview.value = URL.createObjectURL(file);
    }
};

const removeCategoryImage = () => {
    categoryImage.value = null;
    if (categoryPreview.value) {
        URL.revokeObjectURL(categoryPreview.value);
        categoryPreview.value = null;
    }
};
</script>

<template>
    <Head title="Create Product Category" />

    <div class="form">
        <FormHeader :backUrl="productCategoryRoutes.index().url" title="Create Product Category" />

        <Form :action="productCategoryRoutes.store.url()" method="post" v-slot="{ errors, processing }">
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

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="category_image">Category Image</Label>
                    <div class="relative w-40 h-40">
                        <div class="w-40 h-40 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                            <img v-if="categoryPreview" :src="categoryPreview" class="w-full h-full object-cover" />
                            <div v-else class="text-center">
                                <ImagePlus class="w-10 h-10 text-gray-400 mx-auto mb-2" />
                                <p class="text-sm text-gray-500">Click to upload category image</p>
                            </div>
                        </div>
                        <button
                            v-if="categoryPreview"
                            type="button"
                            @click="removeCategoryImage"
                            class="absolute -top-1 -right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                        >
                            <X class="w-3 h-3" />
                        </button>
                        <label class="absolute inset-0 cursor-pointer">
                            <input type="file" name="category_image" accept="image/*" class="hidden" @change="handleCategoryImageChange" />
                        </label>
                    </div>
                    <InputError :message="errors.category_image" />
                </div>
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create Category
                </Button>

                <div>
                    <Link :href="productCategoryRoutes.index().url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </div>
        </Form>
    </div>
</template>