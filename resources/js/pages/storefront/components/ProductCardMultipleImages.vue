<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { Product } from '@/types/product';
import ProductDetails from './ProductDetails.vue';

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

// Detect whether this device supports hover (desktop mouse vs touch)
const supportsHover = ref(false);

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
    // Only auto-slide on devices that actually support hover
    if (!supportsHover.value) {
        return;
    }

    if (!hasMultipleImages.value) {
        return;
    }

    if (autoSlideInterval) {
        return; // already running
    }

    autoSlideInterval = setInterval(nextImage, 3000);
};

const stopAutoSlide = () => {
    if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
    }
};

// Only trigger the hover handlers if the device supports hover
const handleMouseEnter = () => {
    if (!supportsHover.value) {
        return;
    }

    isHovered.value = true;
    startAutoSlide();
};

const handleMouseLeave = () => {
    if (!supportsHover.value) {
        return;
    }

    isHovered.value = false;
    stopAutoSlide();
    // Optionally reset to first image on leave:
    currentImageIndex.value = 0;
};

onMounted(() => {
    // Feature-detect hover support
    supportsHover.value = window.matchMedia('(hover: hover)').matches;
});

onUnmounted(() => {
    stopAutoSlide();
});
</script>

<template>
    <div class="bg-gray-50 rounded-xl shadow-sm hover:shadow-lg overflow-hidden transition-shadow duration-300 flex flex-col h-full"
         @mouseenter="handleMouseEnter"
         @mouseleave="handleMouseLeave">

        <!-- Image Carousel -->
        <div class="product__image relative overflow-hidden bg-gray-100 aspect-square w-full">
            <img
                :src="currentImage?.card_url || currentImage?.url || '/assets/images/default-image.png'"
                :alt="currentImage?.alt || product.slug"
                class="w-full h-full object-cover"
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

            <div class="product__badge absolute top-2 left-2 flex flex-col gap-1">
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

        <ProductDetails :product="product" />
    </div>
</template>
