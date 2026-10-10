<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import AddToCartButton from '@/pages/storefront/components/AddToCartButton.vue';
import productDetailsRoute from '@/routes/product-details';

const {formatPrice} = usePriceFormatter();

interface Product {
    id: number;
    name: string;
    slug: string;
    price: number;
    stock: number;
    sku: string;
    category_name: string;
    is_featured: boolean;
    is_new: boolean;
    is_active: boolean;
    thumbnail_url: string;
}

defineProps<{
    product: Product;
}>();

const isHovered = ref(false);
</script>

<template>
    <div class="bg-white shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group">
        
        <!-- Image Carousel -->
        <div class="relative overflow-hidden bg-gray-100 aspect-square h-70 w-full">
            <img 
                :src="product.thumbnail_url" 
                :alt="product.slug"
                class="w-full h-full object-cover transition-transform duration-500"
                :class="{ 'scale-105': isHovered }"
                @mouseenter="isHovered = true"
                @mouseleave="isHovered = false"
                loading="lazy"
            />

            <!-- Badges -->
            <div class="absolute top-2 left-2 flex flex-col gap-1">
                <span v-if="product.is_new" 
                      class="bg-green-500 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow-lg">
                    New Arrival
                </span>
            </div>

            <!-- Wishlist Button -->
            <!-- <button class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm p-2 rounded-full shadow-md hover:bg-white transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 hover:text-red-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
            </button> -->
        </div>

        <!-- Product Info -->
        <div class="p-4 content">
            <div class="extras flex justify-between items-center mb-4">
                <span class="text-xs text-gray-500 normal-case tracking-wider">{{ product.category_name }}</span>

                <!-- TODO: add the actual stock count -->
                <div class="flex items-center gap-1">
                    <span :class="[
                        'inline-block w-2 h-2 rounded-full',
                        product.stock > 10 ? 'bg-green-500' :
                        product.stock > 0 ? 'bg-yellow-500' : 'bg-red-500'
                    ]"></span>
                    <span class="text-xs text-gray-500">
                        {{ product.stock > 0 ? 'In stock' : 'Out of stock' }}
                    </span>
                </div>
            </div>

            <div class="info space-y-1 mb-4">
                <p class="text-base font-semibold text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1">
                    <Link :href="productDetailsRoute.index(product.slug)">{{ product.name }}</Link>
                </p>
    
                <!-- Price -->
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-2">
                        <span class="text-base font-semibold text-gray-900">{{ formatPrice(product.price) }}</span>
                        <!-- TODO: add discounted price if available -->
                    </p>
                </div>
            </div>
            

            <!-- Add to Cart Button -->
            <AddToCartButton 
                :product-slug="product.slug"
                :product-name="product.name"
                :product-price="formatPrice(product.price)"
                variant="whatsapp"
                button-text="Chat on WhatsApp"
            />
        </div>
    </div>
</template>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.aspect-square {
    aspect-ratio: 1 / 1;
}

/* Smooth transitions */
.group {
    transition: all 0.3s ease;
}
</style>