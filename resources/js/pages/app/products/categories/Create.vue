<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue';
import { Link, Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import FormHeader from '@/components/custom/FormHeader.vue';
import productCategoryRoutes from '@/routes/product-categories';

// ✅ No props needed for create
const form = useForm({
    name: '',
    description: '',
    is_active: true,
    image: null as File | null, // ✅ Single image, not array
});

const imagePreview = ref<string | null>(null);

const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    // Check file size
    if (file.size > MAX_FILE_SIZE) {
        alert(`Image exceeds 2MB limit.`);
        target.value = '';
        return;
    }

    // Check file type
    if (!file.type.startsWith('image/')) {
        alert('Please upload a valid image file.');
        target.value = '';
        return;
    }

    // ✅ Set single image
    form.image = file;
    
    // Create preview
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }
    imagePreview.value = URL.createObjectURL(file);
};

const removeImage = () => {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }
    imagePreview.value = null;
    form.image = null;
};

const submitForm = () => {
    // ✅ Use proper route for product categories
    form.post(productCategoryRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            // Cleanup preview
            if (imagePreview.value) {
                URL.revokeObjectURL(imagePreview.value);
                imagePreview.value = null;
            }
        }
    });
};

// ✅ Cleanup on component unmount
onBeforeUnmount(() => {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }
});
</script>

<template>
    <Head title="Create Product Category" />

    <div class="form max-w-4xl mx-auto p-6">
        <FormHeader :backUrl="productCategoryRoutes.index().url" title="Create Product Category" />

        <form @submit.prevent="submitForm" enctype="multipart/form-data" class="space-y-8">
            <!-- Basic Information -->
            <div class="form-section bg-white rounded-lg shadow-sm p-6">
                <h3 class="section-title text-lg font-semibold text-gray-900 mb-4">Category Information</h3>

                <div class="space-y-4">
                    <div class="inputs-group">
                        <Label for="name" class="required">Category Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="e.g., Sneakers, Boots, Sandals"
                            autofocus
                            class="mt-1"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="inputs-group">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe this product category..."
                            class="mt-1"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="inputs-group">
                        <div class="flex items-center gap-2">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                v-model="form.is_active"
                                class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <Label for="is_active" class="cursor-pointer">Active</Label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Inactive categories won't be visible to customers</p>
                    </div>
                </div>
            </div>

            <!-- Category Image -->
            <div class="form-section bg-white rounded-lg shadow-sm p-6">
                <h3 class="section-title text-lg font-semibold text-gray-900 mb-4">Category Image</h3>
                
                <div class="inputs-group">
                    <div class="flex flex-col items-center justify-center">
                        
                        <!-- Image Preview -->
                        <div v-if="imagePreview" class="relative w-48 h-48 rounded-lg overflow-hidden border border-gray-200">
                            <img 
                                :src="imagePreview" 
                                class="w-full h-full object-cover"
                                alt="Category image preview"
                            />

                            <button 
                                type="button" 
                                @click="removeImage"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors"
                            >
                                <X class="w-3 h-3" />
                            </button>
                        </div>

                        <!-- Upload Button -->
                        <label
                            v-else
                            class="flex flex-col items-center justify-center w-48 h-48 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-500 hover:bg-indigo-50 transition-colors"
                        >
                            <ImagePlus class="w-12 h-12 text-gray-400 mb-2" />
                            <span class="text-sm text-gray-500">Upload Image</span>
                            <span class="text-xs text-gray-400 mt-1">JPG, PNG, WebP (max 2MB)</span>

                            <input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleImageChange"
                            />
                        </label>

                    </div>

                    <InputError :message="form.errors.image" />
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-end">
                <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                    <Spinner v-if="form.processing" class="mr-2" />
                    Create Category
                </Button>
                
                <Link :href="productCategoryRoutes.index().url">
                    <Button type="button" variant="outline" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                </Link>
            </div>
        </form>
    </div>
</template>

<style scoped>
.required::after {
    content: ' *';
    color: #ef4444;
}

.form {
    max-width: 1200px;
    margin: 0 auto;
}

.section-title {
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.75rem;
}
</style>