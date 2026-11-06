<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { ref } from 'vue';
import FormHeader from '@/components/custom/FormHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import productBrandRoutes from '@/routes/product-brands';

const brandPreview = ref<string | null>(null);
const brandImage = ref<File | null>(null);

const handleCategoryImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        brandImage.value = file;
        brandPreview.value = URL.createObjectURL(file);
    }
};

const removeCategoryImage = () => {
    brandImage.value = null;

    if (brandPreview.value) {
        URL.revokeObjectURL(brandPreview.value);
        brandPreview.value = null;
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
                        :tabindex="1"
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
                    :tabindex="2"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="inputs-group">
                <div class="flex items-center justify-between">
                    <Label for="is_active" class="flex items-center space-x-3">
                        <Checkbox id="is_active" name="is_active" :default-value="true" :tabindex="3" />
                        <span>Active</span>
                    </Label>
                </div>
                <p class="text-xs text-gray-500 mt-1">Inactive brands won't be visible to customers</p>
            </div>

            <div class="inputs-group-wrapper">
                <div class="inputs-group">
                    <Label for="image">Brand Image</Label>
                    <div class="relative w-40 h-40">
                        <div class="w-40 h-40 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                            <img v-if="brandPreview" :src="brandPreview" class="w-full h-full object-contain" />
                            <div v-else class="text-center">
                                <ImagePlus class="w-10 h-10 text-gray-400 mx-auto mb-2" />
                                <p class="text-sm text-gray-500">Click to upload brand image</p>
                            </div>
                        </div>
                        <button
                            v-if="brandPreview"
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