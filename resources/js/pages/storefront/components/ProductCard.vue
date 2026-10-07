<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import AddToCartButton from '@/pages/storefront/components/AddToCartButton.vue';
import productDetailsRoute from '@/routes/products'; // TODO: correct this import
import type { Product } from '@/types/product';

const {formatPrice} = usePriceFormatter();

const props = withDefaults(defineProps<{
    product: Product;
    showStockIndicator?: boolean;
    showAddToCart?: boolean;
    buttonText?: string;
}>(), {
    showStockIndicator: true,
    showAddToCart: true,
    buttonText: 'Add To Cart'
});

const currentImageIndex = ref(0);
const isHovered = ref(false);
let autoSlideInterval: ReturnType<typeof setInterval> | null = null;

const currentImage = computed(() => {
    return props.product.images[currentImageIndex.value] || props.product.images[0];
});

const hasMultipleImages = computed(() => {
    return props.product.images && props.product.images.length > 1;
});

const nextImage = () => {
    if (hasMultipleImages.value) {
        currentImageIndex.value = (currentImageIndex.value + 1) % props.product.images.length;
    }
};

const prevImage = () => {
    if (hasMultipleImages.value) {
        currentImageIndex.value = currentImageIndex.value === 0 
            ? props.product.images.length - 1 
            : currentImageIndex.value - 1;
    }
};

const goToImage = (index: number) => {
    currentImageIndex.value = index;
};

const startAutoSlide = () => {
    if (hasMultipleImages.value) {
        autoSlideInterval = setInterval(nextImage, 3000);
    }
};

const stopAutoSlide = () => {
    if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
    }
};

// const getStars = (rating: number) => {
//     const fullStars = Math.floor(rating);
//     const hasHalfStar = rating % 1 >= 0.5;

//     return { fullStars, hasHalfStar };
// };

onMounted(() => {
    startAutoSlide();
});

onUnmounted(() => {
    stopAutoSlide();
});
</script>

<template>
    <div class="bg-white shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group"
         @mouseenter="stopAutoSlide"
         @mouseleave="startAutoSlide">
        
        <!-- Image Carousel -->
        <div class="relative overflow-hidden bg-gray-100 aspect-square h-70 w-full">
            <img 
                :src="currentImage?.url || '/assets/images/default-image.png'" 
                :alt="currentImage?.alt || product.slug"
                class="w-full h-full object-cover transition-transform duration-500"
                :class="{ 'scale-105': isHovered }"
                @mouseenter="isHovered = true"
                @mouseleave="isHovered = false"
                loading="lazy"
            />
            
            <!-- Navigation Arrows -->
            <button v-if="hasMultipleImages" 
                    @click.stop="prevImage"
                    class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/90 backdrop-blur-sm p-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-white shadow-lg">
                <svg class="w-4 h-4 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button v-if="hasMultipleImages" 
                    @click.stop="nextImage"
                    class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/90 backdrop-blur-sm p-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-white shadow-lg">
                <svg class="w-4 h-4 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <!-- Dots Indicator -->
            <div v-if="hasMultipleImages" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                <button v-for="(image, index) in product.images" :key="index"
                        @click.stop="goToImage(index)"
                        class="transition-all duration-300"
                        :class="[
                            'rounded-full',
                            currentImageIndex === index 
                                ? 'w-3 h-1.5 bg-white' 
                                : 'w-1.5 h-1.5 bg-white/60'
                        ]">
                </button>
            </div>

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

            <!-- Image Counter -->
            <span v-if="hasMultipleImages" 
                  class="absolute bottom-3 right-3 bg-black/60 text-white text-xs px-2 py-1 rounded-md backdrop-blur-sm">
                {{ currentImageIndex + 1 }} / {{ product.images.length }}
            </span>
        </div>

        <!-- Product Info -->
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500 normal-case tracking-wider">{{ product.category_name }}</span>

                <!-- TODO: add the actual stock count -->
                <div class="flex items-center gap-1">
                    <span :class="[
                        'inline-block w-2 h-2 rounded-full',
                        product.current_stock > 10 ? 'bg-green-500' :
                        product.current_stock > 0 ? 'bg-yellow-500' : 'bg-red-500'
                    ]"></span>
                    <span class="text-xs text-gray-500">
                        {{ product.current_stock > 0 ? 'In stock' : 'Out of stock' }}
                    </span>
                </div>
            </div>
            
            <h3 class="text-base font-semibold text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1">
                <Link :href="productDetailsRoute.index(product.slug)">{{ product.name }}</Link>
            </h3>

            <!-- Price -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-gray-900">Ksh {{ formatPrice(product.price) }}</span>
                    <!-- TODO: add discounted price if available -->
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