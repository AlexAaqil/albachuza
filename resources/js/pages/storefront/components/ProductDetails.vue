<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePriceFormatter } from '@/composables/usePriceFormatter';
import AddToCartButton from '@/pages/storefront/components/AddToCartButton.vue';
import productDetailsRoute from '@/routes/product-details';
import type {Product} from '@/types/product';

const {formatPrice} = usePriceFormatter();

const props = defineProps<{
    product: Product;
}>();

const onAddToCart = () => {
    // TODO: call your cart store / POST endpoint
    console.log('add to cart', props.product.slug);
};
</script>

<template>
    <div class="product__info p-4 flex flex-col gap-2 flex-1">
        <div class="meta__row flex justify-between items-center gap-2">
            <span class="product_sku text-xs text-gray-500 normal-case tracking-wider">
                {{ product.sku }}
            </span>

            <span class="category__name text-xs text-gray-500 normal-case tracking-wider truncate">
                {{ product.category_name }}
            </span>

            <div class="stock__count flex items-center gap-1">
                <span :class="[
                    'inline-block w-2 h-2 rounded-full',
                    product.current_stock > product.low_stock_threshold ? 'bg-green-500' :
                    product.current_stock > 0 ? 'bg-yellow-500' : 'bg-red-500'
                ]"></span>
                <span class="text-xs text-gray-500">
                    {{ product.current_stock }}
                </span>
            </div>
        </div>

        <div class="product__details space-y-2 mb-2">
            <p class="product__name text-base font-semibold text-gray-900 leading-snug line-clamp-2">
                <Link :href="productDetailsRoute.index(product.slug)" class="hover:text-indigo-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
                    {{ product.name }}
                </Link>
            </p>

            <div class="product__price flex items-center gap-2">
                <span class="text-base font-semibold text-gray-900">Ksh. {{ formatPrice(product.price) }}</span>
            </div>
        </div>

        <div class="cart__btn mt-auto pt-1">
            <AddToCartButton
                :product-slug="product.slug"
                :product-name="product.name"
                :product-price="product.price"
                variant="whatsapp"
                button-text="Chat On Whatsapp"
            />
        </div>
    </div>
</template>