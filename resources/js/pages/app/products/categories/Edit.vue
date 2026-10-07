<script setup lang="ts">
import { ref, onUnmounted } from 'vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import FormHeader from '@/components/custom/FormHeader.vue';
import productCategoryRoutes from '@/routes/product-categories';

interface Category {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    image_url: string | null;   // if your model/resource exposes it
}

const props = defineProps<{ product_category: Category }>();

// Preview: either the existing image URL or an object URL for a newly-picked file
const imagePreview = ref<string | null>(props.product_category.image_url ?? null);

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    // Revoke any previous object URL
    if (imagePreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }

    imagePreview.value = URL.createObjectURL(file);
};

const removeImage = () => {
    if (imagePreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }
    // Setting to null hides the preview; the file input itself must be cleared separately
    imagePreview.value = null;
};

onUnmounted(() => {
    if (imagePreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }
});
</script>

<template>
    <Head :title="`Edit ${product_category.name}`" />

    <div class="form">
        <FormHeader
            :backUrl="productCategoryRoutes.index().url"
            :title="`Edit ${product_category.name}`"
        />

        <Form
            :action="productCategoryRoutes.update(product_category.uuid).url"
            method="put"
            v-slot="{ errors, processing }"
        >
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
                        :default-value="product_category.name"
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
                    placeholder="Describe the product_category..."
                    :default-value="product_category.description ?? ''"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="image">Category Image</Label>

                    <div class="relative w-40 h-40">
                        <div class="w-40 h-40 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                            <img
                                v-if="imagePreview"
                                :src="imagePreview"
                                class="w-full h-full object-contain"
                                alt="Category image preview"
                            />
                            <div v-else class="text-center">
                                <ImagePlus class="w-10 h-10 text-gray-400 mx-auto mb-2" />
                                <p class="text-sm text-gray-500">Click to upload category image</p>
                            </div>
                        </div>

                        <input type="hidden" name="remove_image" :value="imagePreview === null ? '1' : '0'" />

                        <button
                            v-if="imagePreview"
                            type="button"
                            @click="removeImage"
                            class="absolute -top-1 -right-1 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                            aria-label="Remove image"
                        >
                            <X class="w-3 h-3" />
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

                    <p v-if="product_category.image" class="text-xs text-gray-500 mt-1">
                        Current file: <span class="font-mono">{{ product_category.image }}</span>
                    </p>

                    <InputError :message="errors.image" />
                </div>
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Update Category
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