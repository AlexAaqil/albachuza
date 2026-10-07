<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, Form } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import FormHeader from '@/components/custom/FormHeader.vue';
import productCategoryRoutes from '@/routes/product-categories';

const props = defineProps<{
    product_category: {
        id: number;
        uuid: string;
        name: string;
        slug: string;
        description: string | null;
        image: string | null;
        thumbnail_url: string | null;
        is_active: boolean;
    };
}>();

const imagePreview = ref<string | null>(props.product_category.thumbnail_url);

// 1. Initialize Inertia's useForm hook
const form = useForm({
    _method: 'PUT', // Method spoofing for file uploads
    name: props.product_category.name,
    description: props.product_category.description ?? '',
    is_active: props.product_category.is_active,
    image: null as File | null,
});

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.image = file; // 2. Assign file object directly to form state
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeLogo = () => {
    form.image = null;
    imagePreview.value = null;
};

// 3. Submit Handler
const handleSubmit = () => {
    // Crucial: Use .post() with _method: 'PUT' inside payload for Laravel file uploads!
    form.post(productCategoryRoutes.update(props.product_category.uuid).url, {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Edit Product Category" />

    <div class="form max-w-4xl mx-auto p-6">
        <FormHeader :backUrl="productCategoryRoutes.index().url" title="Edit Category" />

        <Form 
            :action="productCategoryRoutes.update(props.product_category.uuid).url" 
            method="post" 
            v-slot="{ errors, processing }"
            @submit="handleSubmit"
        >
            <input type="hidden" name="_method" value="PUT" />

            <div class="space-y-6">
                <div class="rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">Category Information</h3>
                    
                    <div class="space-y-4">
                        <div class="inputs-group">
                            <Label for="name" class="required">Category Name</Label>
                            <Input
                                id="name"
                                type="text"
                                autofocus
                                name="name"
                                v-model="form.name"
                                placeholder="Category name"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="inputs-group">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                name="description"
                                rows="4"
                                v-model="form.description"
                                placeholder="Describe your product category..."
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="inputs-group">
                            <div class="flex items-center gap-2">
                                <input 
                                    type="checkbox" 
                                    id="is_active" 
                                    v-model="form.is_active"
                                    :checked="props.product_category.is_active"
                                    value="1"
                                    class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <Label for="is_active" class="cursor-pointer">Active</Label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Inactive categories won't be visible to customers</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">Category Image</h3>
                    
                    <div class="inputs-group">
                        <div class="flex flex-col justify-center">
                            <div class="relative w-48 h-48">
                                <div class="w-48 h-48 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                                    <img 
                                        v-if="imagePreview" 
                                        :src="imagePreview" 
                                        class="w-full h-full object-cover" 
                                    />
                                    <div v-else class="text-center">
                                        <ImagePlus class="w-12 h-12 text-gray-400 mx-auto mb-2" />
                                        <p class="text-sm text-gray-500">Click to upload</p>
                                    </div>
                                </div>
                                
                                <button
                                    v-if="imagePreview && imagePreview !== props.product_category.thumbnail_url"
                                    type="button"
                                    @click="removeLogo"
                                    class="absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                                
                                <label class="absolute inset-0 cursor-pointer">
                                    <input 
                                        type="file" 
                                        name="image" 
                                        accept="image/*" 
                                        class="hidden" 
                                        @change="handleImageChange" 
                                    />
                                </label>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">
                                Current: {{ props.product_category.image || 'No image uploaded' }}
                            </p>
                            <InputError :message="errors.image" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <Button type="submit" :disabled="processing" class="w-full sm:w-auto">
                        <Spinner v-if="processing" class="mr-2" />
                        Update Category
                    </Button>

                    <Link :href="productCategoryRoutes.index().url">
                        <Button type="button" variant="outline" class="w-full sm:w-auto">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </div>
        </Form>
    </div>
</template>