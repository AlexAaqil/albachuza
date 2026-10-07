<script setup lang="ts">
import { computed } from 'vue';

// ✅ SINGLE defineProps with defaults
const props = withDefaults(defineProps<{
    productSlug: string;
    productName?: string;
    productPrice?: number | string;
    buttonText?: string;
    variant?: 'whatsapp' | 'default';
    size?: 'sm' | 'md' | 'lg';
}>(), {
    buttonText: 'Interested? Chat on WhatsApp',
    variant: 'whatsapp',
    size: 'md'
});

// WhatsApp phone number - replace with your actual number
const WHATSAPP_PHONE = '254746055487'; // Kenya phone number format

// Build WhatsApp message
const whatsappMessage = computed(() => {
    const baseUrl = 'https://wa.me/';
    const phone = WHATSAPP_PHONE;
    
    // Build the message
    let message = `Hi! I'm interested in this product:`;
    message += `\n🔗 Product: https://yourdomain.com/products/${props.productSlug}`;
    
    if (props.productName) {
        message += `\n📦 ${props.productName}`;
    }
    
    if (props.productPrice) {
        message += `\n💰 Price: ${props.productPrice}`;
    }
    
    message += `\n\nCan you please provide more information?`;
    
    // Encode for URL
    const encodedMessage = encodeURIComponent(message);
    return `${baseUrl}${phone}?text=${encodedMessage}`;
});

const openWhatsApp = () => {
    window.open(whatsappMessage.value, '_blank');
};

// Size classes
const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2.5 text-base',
    lg: 'px-6 py-3 text-lg'
};

// Variant classes
const variantClasses = {
    whatsapp: 'bg-green-500 hover:bg-green-600 text-white shadow-md hover:shadow-lg',
    default: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-md hover:shadow-lg'
};
</script>

<template>
    <button
        @click="openWhatsApp"
        :class="[
            'font-medium transition-all duration-200 flex items-center justify-center gap-2 w-full',
            'hover:scale-[1.02] active:scale-95',
            sizeClasses[size],
            variantClasses[variant]
        ]"
    >
        <!-- WhatsApp Icon -->
        <svg 
            v-if="variant === 'whatsapp'"
            xmlns="http://www.w3.org/2000/svg" 
            class="w-5 h-5" 
            viewBox="0 0 24 24" 
            fill="currentColor"
        >
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.523 3.651 1.455 5.173l-1.012 3.773a1 1 0 001.134 1.134l3.773-1.012A9.959 9.959 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.958 7.958 0 01-4.249-1.213l-.248-.149-2.799.75.75-2.799-.149-.248A7.958 7.958 0 014 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/>
        </svg>

        <!-- Shopping Cart Icon -->
        <svg 
            v-else
            xmlns="http://www.w3.org/2000/svg" 
            class="w-5 h-5" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>

        {{ buttonText }}
    </button>
</template>

<style scoped>
/* Optional: Add any custom styles */
</style>