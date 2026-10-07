<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import FormHeader from '@/components/custom/FormHeader.vue';
import productBrandRoutes from '@/routes/product-brands';

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
    <Head title="Create Brand" />

    <div class="form">
        <FormHeader :backUrl="productBrandRoutes.index().url" title="Create Brand" />

        <Form :action="productBrandRoutes.store.url()" method="post" v-slot="{ errors, processing }">
            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="name" class="required">Brand Name</Label>
                    <Input
                        id="name"
                        type="text"
                        autofocus
                        autocomplete="name"
                        name="name"
                        placeholder="Brand name"
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
                    placeholder="Describe the brand..."
                />
                <InputError :message="errors.description" />
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="image">Brand Image</Label>
                    <div class="relative w-40 h-40">
                        <div class="w-40 h-40 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                            <img v-if="categoryPreview" :src="categoryPreview" class="w-full h-full object-contain" />
                            <div v-else class="text-center">
                                <ImagePlus class="w-10 h-10 text-gray-400 mx-auto mb-2" />
                                <p class="text-sm text-gray-500">Click to upload brand image</p>
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
                            <input type="file" name="image" accept="image/*" class="hidden" @change="handleCategoryImageChange" />
                        </label>
                    </div>
                    <InputError :message="errors.image" />
                </div>
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create Brand
                </Button>

                <div>
                    <Link :href="productBrandRoutes.index().url">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </div>
        </Form>
    </div>
</template>