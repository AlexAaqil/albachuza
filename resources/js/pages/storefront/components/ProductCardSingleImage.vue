<script setup lang="ts">
import { ref } from 'vue';
import type {Product} from '@/types/product';
import ProductDetails from './ProductDetails.vue';

defineProps<{
    product: Product;
}>();

const isHovered = ref(false);
</script>

<template>
    <div class="bg-gray-50 rounded-xl shadow-sm hover:shadow-lg overflow-hidden transition-shadow duration-300 flex flex-col h-full">
        <div class="product__image relative overflow-hidden bg-gray-100 aspect-square w-full">
            <img 
                :src="product.card_image_url || product.thumbnail_url"
                :alt="product.name"
                class="w-full h-full object-cover transition-transform duration-500 ease-in-out motion-reduce:transition-none motion-reduce:transform-none"
                :class="{ 'scale-105': isHovered }"
                @mouseenter="isHovered = true"
                @mouseleave="isHovered = false"
                loading="lazy"
            />

            <div class="product__badge absolute top-2 left-2 flex flex-col gap-1">
                <span v-if="product.is_new" 
                      class="bg-green-500 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow-lg">
                    New Arrival
                </span>
            </div>
        </div>

        <ProductDetails :product="product" />
    </div>
</template>
