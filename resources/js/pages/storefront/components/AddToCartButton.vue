<script setup lang="ts">
import { computed } from 'vue';
import { useAppConfig } from '@/composables/useAppConfig';

const props = withDefaults(defineProps<{
    productSlug: string;
    productName?: string;
    /** Pass a RAW number, not a pre-formatted string. */
    productPrice?: number;
    buttonText?: string;
    variant?: 'whatsapp' | 'default';
    size?: 'sm' | 'md' | 'lg';
}>(), {
    buttonText: 'Add to Cart',
    variant: 'default',
    size: 'md',
});

const emit = defineEmits<{
    (e: 'add-to-cart', payload: { slug: string; name?: string; price?: number }): void;
}>();

const { socials } = useAppConfig();

const whatsappUrl = computed(() => {
    const baseUrl = socials.value?.whatsapp_url;

    if (!baseUrl) {
        return '#';
    }

    // Use the real origin, not a config value that may not exist
    const siteUrl = typeof window !== 'undefined'
        ? window.location.origin
        : '';
    const productUrl = `${siteUrl}/product-details/${props.productSlug}`;

    let message = `Hi! I'm interested in this product:\n\n`;
    message += `*Link:* ${productUrl}\n\n`;

    if (props.productName) {
        message += `*Product:* ${props.productName}\n`;
    }

    if (props.productPrice != null) {
        message += `*Price:* Ksh. ${props.productPrice.toLocaleString()}\n`;
    }

    message += `\nPlease provide more information!`;

    return `${baseUrl}?text=${encodeURIComponent(message)}`;
});

const handleClick = () => {
    if (props.variant === 'whatsapp') {
        if (whatsappUrl.value === '#') {
            console.warn('[AddToCartButton] WhatsApp URL is not configured.');

            return;
        }

        window.open(whatsappUrl.value, '_blank', 'noopener,noreferrer');

        return;
    }

    // Default variant: add to cart
    emit('add-to-cart', {
        slug: props.productSlug,
        name: props.productName,
        price: props.productPrice,
    });
};

const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2.5 text-base',
    lg: 'px-6 py-3 text-lg',
};

const variantClasses = {
    whatsapp: 'bg-green-500 hover:bg-green-600 text-white shadow-md hover:shadow-lg',
    default: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-md hover:shadow-lg',
};
</script>

<template>
    <button
        type="button"
        @click="handleClick"
        :class="[
            'font-medium transition-all duration-200 flex items-center justify-center gap-2 w-full rounded-md',
            'hover:scale-[1.02] active:scale-95',
            sizeClasses[size],
            variantClasses[variant],
        ]"
    >
        <!-- WhatsApp icon -->
        <svg
            v-if="variant === 'whatsapp'"
            xmlns="http://www.w3.org/2000/svg"
            class="w-5 h-5"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
        >
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.523 3.651 1.455 5.173l-1.012 3.773a1 1 0 001.134 1.134l3.773-1.012A9.959 9.959 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.958 7.958 0 01-4.249-1.213l-.248-.149-2.799.75.75-2.799-.149-.248A7.958 7.958 0 014 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/>
        </svg>

        <!-- Cart icon -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-cart-plus preview-icon w-5 h-5"><path d="M16 5h6"/><path d="M19 2v6"/><path d="m2.05 2.05 1.099-.028a1 1 0 011.008.815l2.69 14.347A1 1 0 007.83 18H18"/><path d="M4.564 5H12"/><path d="M6.25 14h12.712a2 2 0 001.991-1.57l.172-1.041"/><circle cx="18" cy="20" r="2"/><circle cx="8" cy="20" r="2"/></svg>

        {{ buttonText }}
    </button>
</template>