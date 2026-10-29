<script setup lang="ts">
import { Link, Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormHeader from '@/components/custom/FormHeader.vue';
import ProductImagePicker from '@/components/custom/ProductImagePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import productRoutes from '@/routes/products';
import type { ProductImageItem } from '@/types/product';

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
    size: '',
    barcode: '',
    is_featured: false,
    is_active: true,
    is_new: false,
    track_inventory: true,
    low_stock_threshold: 0,
    product_category_id: null as number | null,
    product_brand_id: null as number | null,
    images: [] as File[],
});

const imageItems = ref<ProductImageItem[]>([]);

const submitForm = () => {
    form.images = imageItems.value.filter((item) => item.file).map((item) => item.file as File);

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

                    <div class="inputs-group">
                        <Label for="size">Shoe Size</Label>
                        <Input
                            id="size"
                            v-model="form.size"
                            type="text"
                            placeholder="e.g (42 U.K)"
                        />
                        <InputError :message="form.errors.size" />
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

                <!-- <div class="inputs-group-wrapper">
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
                </div> -->

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
                    <ProductImagePicker v-model="imageItems" />

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