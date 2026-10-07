<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Sun, Moon, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();
const page = usePage();

const user = computed(() => page.props.auth?.user);
</script>

<template>
    <header class="guest_navbar py-4 px-4 lg:px-16 border-b border-border sticky top-0 z-50 bg-background/95 backdrop-blur-sm transition-all duration-300">
        <nav class="flex items-center justify-between">
            <div class="branding">
                <Link href="/" class="font-bold">Albachuza</Link>
            </div>

            <div class="links flex items-center gap-8">
                <Link href="/dashboard" v-if="user">Dashboard</Link>
                <Link href="/shop">Shop</Link>
                <Link href="/deals-page">Deals & Offers</Link>
                <Link href="/business-community">Community</Link>
                <Link href="/contact">Contact</Link>
            </div>

            <div class="extras flex items-center justify-center gap-4">
                <button @click="updateAppearance(appearance === 'light' ? 'dark' : 'light')" class="toggle-theme" title="Toggle theme">
                    <Sun v-if="appearance === 'light'" class="icon sun-icon w-5 h-5" />
                    <Moon v-else class="icon moon-icon w-5 h-5" />
                </button>

                <Link href="/cart" title="Cart" class="cart relative">
                    <ShoppingCart class="icon shopping-cart-icon" />
                    <span class="cart_count absolute -right-2 -top-2 w-5 h-5 flex items-center justify-center text-xs font-medium bg-green-600 text-white rounded-full p-1">
                        <!-- {{ cartStore.itemCount > 99 ? '99+' : cartStore.itemCount }} -->
                          99
                    </span>
                </Link>

                <div v-if="user" class="loggedin_user_menu"></div>

                <div v-else class="auth_pages_links flex items-center gap-2">
                    <Link href="/login" class="px-3 py-1.5 bg-foreground text-background font-medium tracking-wide rounded-sm hover:opacity-90">Login</Link>
                </div>
            </div>
        </nav>
    </header>
</template>
