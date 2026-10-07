<script setup lang="ts">
import { Link, Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { ref } from 'vue';
import FormHeader from '@/components/custom/FormHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import productRoutes from '@/routes/products';

interface Category {
    id: number;
    name: string;
}

interface Brand {
    id: number;
    name: string;
}

defineProps<{
    product_categories: Category[];
    product_brands: Brand[];
}>();

const form = useForm({
    name: '',
    description: '',
    cost_price: '',
    price: '',
    sku: '',
    barcode: '',
    is_featured: false,
    is_active: true,
    is_new: false,
    track_inventory: false,
    low_stock_threshold: 5,
    product_category_id: null as number | null,
    product_brand_id: null as number | null,
    images: [] as File[],
});

const imagePreviews = ref<string[]>([]);

const MAX_IMAGES = 5;
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const MAX_TOTAL_SIZE = 10 * 1024 * 1024; // 10MB

const isMaxImagesReached = () => form.images.length >= MAX_IMAGES;

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = Array.from(target.files || []);

    if (!files.length) {
        return;
    }

    // Max count
    if (form.images.length + files.length > MAX_IMAGES) {
        alert(`Maximum ${MAX_IMAGES} images allowed.`);
        target.value = '';

        return;
    }

    // Per-file size
    for (const file of files) {
        if (file.size > MAX_FILE_SIZE) {
            alert(`${file.name} exceeds 2MB`);
            target.value = '';

            return;
        }
    }

    // Total size
    const currentSize = form.images.reduce((sum, f) => sum + f.size, 0);
    const newSize = files.reduce((sum, f) => sum + f.size, 0);

    if (currentSize + newSize > MAX_TOTAL_SIZE) {
        alert(`Total size cannot exceed 10MB`);
        target.value = '';

        return;
    }

    // Add files + previews
    files.forEach(file => {
        if (!file.type.startsWith('image/')) {
            return;
        }

        form.images.push(file);
        imagePreviews.value.push(URL.createObjectURL(file));
    });

    target.value = '';
};

const removeImage = (index: number) => {
    URL.revokeObjectURL(imagePreviews.value[index]);

    form.images.splice(index, 1);
    imagePreviews.value.splice(index, 1);
};

const submitForm = () => {
    form.post(productRoutes.store.url(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Product" />

    <div class="form">
        <FormHeader :backUrl="productRoutes.index().url" title="Create Product" />

        <form @submit.prevent="submitForm" enctype="multipart/form-data">
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>

                <div class="inputs-group-wrapper">
                    <div class="inputs-group">
                        <Label for="name" class="required">Product Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Product name"
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                </div>

                <div class="inputs-group-wrapper">
                    <div class="inputs-group">
                        <Label for="product_category_id">Product Category</Label>
                        <Select v-model="form.product_category_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select product category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null">None</SelectItem>
                                    <SelectItem 
                                        v-for="option in product_categories" 
                                        :key="option.id"
                                        :value="option.id"
                                    >
                                        {{ option.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.product_category_id" />
                    </div>

                    <div class="inputs-group">
                        <Label for="product_category_id">Brand</Label>
                        <Select v-model="form.product_brand_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select brand category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null">None</SelectItem>
                                    <SelectItem 
                                        v-for="option in product_brands" 
                                        :key="option.id"
                                        :value="option.id"
                                    >
                                        {{ option.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.product_brand_id" />
                    </div>
                </div>

                <div class="inputs-group-wrapper">
                    <div class="inputs-group">
                        <Label for="sku">SKU</Label>
                        <Input
                            id="sku"
                            v-model="form.sku"
                            type="text"
                            placeholder="SKU"
                        />
                        <InputError :message="form.errors.sku" />
                    </div>

                    <div class="inputs-group">
                        <Label for="barcode">Barcode</Label>
                        <Input
                            id="barcode"
                            v-model="form.barcode"
                            type="text"
                            placeholder="3216911047"
                        />
                        <InputError :message="form.errors.barcode" />
                    </div>
                </div>

                <div class="inputs-group-wrapper">
                    <div class="inputs-group">
                        <Label for="cost_price">Cost Price</Label>
                        <Input
                            id="cost_price"
                            v-model="form.cost_price"
                            type="number"
                            step="0.01"
                            placeholder="Cost Price"
                        />
                        <InputError :message="form.errors.cost_price" />
                    </div>

                    <div class="inputs-group">
                        <Label for="price" class="required">Selling Price</Label>
                        <Input
                            id="price"
                            v-model="form.price"
                            type="number"
                            step="0.01"
                            placeholder="Selling Price"
                        />
                        <InputError :message="form.errors.price" />
                    </div>
                </div>

                <div class="inputs-group">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        placeholder="Describe your product..."
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="inputs-group-wrapper-4">
                    <div class="inputs-group">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0" />
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                v-model="form.is_active"
                                value="1"
                                class="w-4 h-4 rounded"
                            />
                            <Label for="is_active">Active</Label>
                        </div>
                    </div>

                    <div class="inputs-group">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_featured" value="0" />
                            <input 
                                type="checkbox" 
                                id="is_featured" 
                                v-model="form.is_featured"
                                value="1"
                                class="w-4 h-4 rounded"
                            />
                            <Label for="is_featured">Featured Product</Label>
                        </div>
                    </div>

                    <div class="inputs-group">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_new" value="0" />
                            <input 
                                type="checkbox" 
                                id="is_new" 
                                v-model="form.is_new"
                                value="1"
                                class="w-4 h-4 rounded"
                            />
                            <Label for="is_new">New</Label>
                        </div>
                    </div>

                    <div class="inputs-group">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="track_inventory" value="0" />
                            <input 
                                type="checkbox" 
                                id="track_inventory" 
                                v-model="form.track_inventory"
                                value="1"
                                class="w-4 h-4 rounded"
                            />
                            <Label for="track_inventory">Track Inventory</Label>
                        </div>
                    </div>
                </div>

                <div v-if="form.track_inventory" class="inputs-group-wrapper">
                    <div class="inputs-group">
                        <Label for="low_stock_threshold">Low Stock Threshold</Label>
                        <Input
                            id="low_stock_threshold"
                            v-model="form.low_stock_threshold"
                            type="number"
                            step="1"
                            min="0"
                            placeholder="e.g. 5"
                        />
                        <p class="text-xs text-gray-500">
                            You'll be alerted when stock drops to or below this number.
                        </p>
                        <InputError :message="form.errors.low_stock_threshold" />
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Product Images</h3>
                
                <div class="inputs-group">
                    <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                        
                        <!-- Existing Images -->
                        <div 
                            v-for="(preview, idx) in imagePreviews" 
                            :key="idx" 
                            class="relative w-full aspect-square rounded-xl overflow-hidden border"
                        >
                            <img :src="preview" class="w-full h-full object-cover" />

                            <button 
                                type="button" 
                                @click="removeImage(idx)"
                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full"
                            >
                                <X class="w-3 h-3" />
                            </button>
                        </div>

                        <!-- Upload Button (acts like a tile) -->
                        <label
                            v-if="!isMaxImagesReached()"
                            class="flex flex-col items-center justify-center border-2 border-dashed rounded-xl aspect-square cursor-pointer hover:bg-gray-50"
                        >
                            <ImagePlus class="w-8 h-8 text-gray-400 mb-1" />
                            <span class="text-xs text-gray-500">Upload</span>

                            <input
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImageChange"
                            />
                        </label>

                    </div>

                    <p class="text-xs text-gray-400 mt-2">
                        Upload up to 5 images. First image will be primary.
                    </p>

                    <InputError :message="form.errors.images" />
                </div>
            </div>

            <div class="submit-buttons">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Create Product
                </Button>

                <div>
                    <Link :href="productRoutes.index().url">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </div>
        </form>
    </div>
</template>