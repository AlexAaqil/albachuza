<script setup lang="ts">
import { computed } from 'vue';
import { usePriceFormatter } from '@/composables/usePriceFormatter';

const props = defineProps<{
    stat: number;
    label: string;
    variant?: 'default' | 'danger' | 'success';
    /** 'currency' | 'percent' | 'number' (default) */
    format?: 'currency' | 'percent' | 'number';
}>();

const { formatPrice } = usePriceFormatter();

const displayValue = computed(() => {
    switch (props.format) {
        case 'currency': return formatPrice(props.stat);
        case 'percent':  return `${props.stat.toFixed(1)}%`;
        default:         return props.stat.toLocaleString();
    }
});

const variantClasses = {
    default: '',
    danger:  'text-red-600',
    success: 'text-green-600',
};
</script>

<template>
    <div class="stat border border-border p-4 rounded-lg space-y-0.5">
        <p class="text-[24px] font-bold" :class="variantClasses[variant ?? 'default']">
            {{ displayValue }}
        </p>
        <p>{{ label }}</p>
        <div class="extras">
            <slot name="extras">
                <span class="text-sm text-muted-foreground">No extra info</span>
            </slot>
        </div>
    </div>
</template>