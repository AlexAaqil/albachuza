<script setup lang="ts">
import { ref } from 'vue';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import type { Product } from '@/types/product';
import AddToCartButton from '../../components/AddToCartButton.vue';
import ProductCardSingleImage from '../../components/ProductCardSingleImage.vue';

const {formatPrice} = usePriceFormatter();

defineProps<{
    product: {
        data: Product
    }
    related_products: {
        data: Product[];
    }
}>();

// 1. Create a reactive reference for the currently selected image
// We default to the first image (index 0)
const selectedImageIndex = ref(0);

// 2. Helper function to switch the image when a thumbnail is clicked
const selectImage = (index: number) => {
    selectedImageIndex.value = index;
};
</script>

<template>
    <div class="product-details-page space-y-15 lg:pb-15">
        <div class="container-fluid product-details-wrapper grid lg:grid-cols-2 gap-8">
            <div class="images-wrapper flex flex-col gap-1 items-center w-full lg:h-[85dvh]">
                <!-- Main Image Display -->
                <!-- We check if product.data.images exists and has items, otherwise we fallback to a placeholder or product.data.image -->
                <div class="main-image-container lg:w-full lg:h-full lg:flex lg:items-center lg:justify-center lg:overflow-hidden">
                    <img 
                        v-if="product.data.images && product.data.images.length > 0" 
                        :src="product.data.images[selectedImageIndex].url" 
                        :alt="product.data.name"
                        class="main-image w-full h-full object-contain"
                    />
                    <img 
                        v-else-if="product.data.thumbnail_url" 
                        :src="product.data.thumbnail_url" 
                        :alt="product.data.name"
                        class="main-image w-full h-full object-contain"
                    />
                    <div v-else class="no-image-placeholder">
                        No Image Available
                    </div>
                </div>

                <!-- Thumbnails Wrapper -->
                <!-- We only show this if there is more than 1 image -->
                <div 
                    v-if="product.data.images && product.data.images.length > 1" 
                    class="thumbnails-wrapper flex gap-4 mt-4"
                >
                    <div 
                        v-for="(image, index) in product.data.images" 
                        :key="index"
                        class="thumbnail-item w-15 h-15 lg:w-20 lg:h-20 p-0.5 border-2 overflow-hidden cursor-pointer"
                        :class="selectedImageIndex === index ? 'border-blue-800' : 'border-transparent'"
                        @click="selectImage(index)"
                    >
                        <img :src="image.url" :alt="image.alt || `${product.data.name} thumbnail ${index + 1}`" class="w-full h-full object-cover" />
                    </div>
                </div>
            </div>

            <div class="product-details">
                <div class="space-y-4 lg:px-8">
                    <h1 class="font-semibold text-heading-text">{{ product.data.name }}</h1>
                    <p class="text-heading-text">Ksh. {{ formatPrice(product.data.price) }}</p>
                    <p v-if="product.data.size" class="flex text-body-label-text">
                        <span class="text-muted-foreground w-20">Size</span>
                        <span class="">: {{ product.data.size }}</span>
                    </p>

                    <div class="actions grid lg:grid-cols-2 gap-8">
                        <AddToCartButton 
                            :product-slug="product.data.slug"
                            :product-name="product.data.name"
                            :product-price="formatPrice(product.data.price)"
                            variant="whatsapp"
                            button-text="Chat on WhatsApp"
                        />

                        <div></div>
                    </div>

                    <div class="product-description mt-8 lg:mt-16" v-if="product.data.description">
                        <div class="whitespace-pre-line">{{ product.data.description }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="related-products" v-if="related_products.data.length > 0">
            <div class="container-fluid">
                <h2 class="text-heading-text font-bold mb-8 uppercase">People Also Bought</h2>

                <div class="grid  lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <ProductCardSingleImage
                        v-for="product in related_products.data"
                        :key="product.id" 
                        :product="product"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.thumbnail-item {
    transition: border-color 0.2s ease;
}

.no-image-placeholder {
    padding: 20px;
    color: #999;
}
</style>
