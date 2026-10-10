<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps<{
    categories: Array<{
        id: number;
        slug: string;
        name: string;
    }>;
    selectedCategory?: string;
}>();

const emit = defineEmits<{
    (e: 'select-category', slug: string): void;
}>();

const scrollContainer = ref<HTMLDivElement | null>(null);
const showLeftArrow = ref(false);
const showRightArrow = ref(false);

const checkScroll = () => {
    if (!scrollContainer.value) {
        return;
    }
    
    const el = scrollContainer.value;
    const scrollLeft = el.scrollLeft;
    const maxScroll = el.scrollWidth - el.clientWidth;
    
    showLeftArrow.value = scrollLeft > 10;
    showRightArrow.value = maxScroll > 10 && scrollLeft < maxScroll - 10;
};

const scroll = (direction: 'left' | 'right') => {
    if (!scrollContainer.value) {
        return;
    }
    
    const el = scrollContainer.value;
    const scrollAmount = el.clientWidth * 0.8;
    
    el.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth'
    });
};

const handleCategoryClick = (slug: string, event: Event) => {
    event.preventDefault(); // Prevent default anchor navigation
    emit('select-category', slug);
};

const handleAllProductsClick = (event: Event) => {
    event.preventDefault(); // Prevent default anchor navigation
    emit('select-category', '');
};

// Check on mount and when window resizes
onMounted(() => {
    checkScroll();
    window.addEventListener('resize', checkScroll);
    
    // Also check after a small delay for any layout shifts
    setTimeout(checkScroll, 100);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkScroll);
});

// Check whenever categories change
watch(() => props.categories, checkScroll);
</script>

<template>
    <section class="Categories">
        <div class="container-fluid relative">
            <div class="categories-wrapper">
                <!-- Left Arrow -->
                <button 
                    v-show="showLeftArrow"
                    class="arrow left" 
                    @click="scroll('left')"
                    @mouseenter="scrollContainer?.scrollBy({ left: -300, behavior: 'smooth' })"
                    aria-label="Scroll left"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <!-- Scrollable Container -->
                <div 
                    ref="scrollContainer"
                    class="categories-scroll"
                    @scroll="checkScroll"
                >
                    <a 
                        href="/" 
                        class="category-link"
                        @click="handleAllProductsClick"
                    >
                        All Products
                    </a>
                    <a 
                        v-for="category in categories" 
                        :key="category.id"
                        :href="`/categories/${category.slug}`"
                        class="category-link"
                        @click="handleCategoryClick(category.slug, $event)"
                    >
                        {{ category.name }}
                    </a>
                </div>

                <!-- Right Arrow -->
                <button 
                    v-show="showRightArrow"
                    class="arrow right" 
                    @click="scroll('right')"
                    @mouseenter="scrollContainer?.scrollBy({ left: 300, behavior: 'smooth' })"
                    aria-label="Scroll right"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.categories-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
}

.categories-scroll {
    display: flex;
    gap: 2rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding: 0.25rem 0;
    flex: 1;
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.categories-scroll::-webkit-scrollbar {
    display: none;
}

.category-link {
    white-space: nowrap;
    padding: 0.5rem 0.25rem;
    color: #4a5568;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
    flex-shrink: 0;
    position: relative;
}

.category-link:hover {
    color: #2d3748;
}

.category-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: #2d3748;
    transform: scaleX(0);
    transition: transform 0.2s;
}

.category-link:hover::after {
    transform: scaleX(1);
}

.category-link:first-child {
    font-weight: 600;
    color: #2d3748;
}

.category-link:first-child::after {
    background: #2d3748;
}

.arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.2s;
    flex-shrink: 0;
    color: #4a5568;
}

.arrow:hover {
    background: #f7fafc;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-50%) scale(1.05);
}

.arrow:active {
    transform: translateY(-50%) scale(0.95);
}

.arrow.left {
    left: -18px;
}

.arrow.right {
    right: -18px;
}

/* Responsive */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 1.5rem;
    }
    
    .arrow {
        width: 30px;
        height: 30px;
    }
    
    .arrow.left {
        left: -10px;
    }
    
    .arrow.right {
        right: -10px;
    }
    
    .categories-scroll {
        gap: 1.25rem;
    }
    
    .category-link {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .container-fluid {
        padding: 0 1rem;
    }
    
    .arrow {
        width: 26px;
        height: 26px;
    }
    
    .arrow svg {
        width: 18px;
        height: 18px;
    }
    
    .arrow.left {
        left: -6px;
    }
    
    .arrow.right {
        right: -6px;
    }
    
    .categories-scroll {
        gap: 1rem;
    }
}
</style>