<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Sun, Moon, Menu, X, User, LayoutDashboard, LogOut, ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useClickOutside } from '@/composables/useClickOutside';

const { appearance, updateAppearance } = useAppearance();
const page = usePage();

const user = computed(() => page.props.auth?.user);

// ---- mobile burger ----
const mobileOpen = ref(false);
const closeMobile = () => (mobileOpen.value = false);

// ---- user dropdown ----
const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);
useClickOutside(userMenuRef, () => (userMenuOpen.value = false));

const toggleUserMenu = () => (userMenuOpen.value = !userMenuOpen.value);

const logout = () => {
    userMenuOpen.value = false;
    router.post('/logout');
};

// nice initial fallback if avatar is missing
const userInitial = computed(() => {
    const name = user.value?.name as string | undefined;
    return name ? name.charAt(0).toUpperCase() : '?';
});

// Use escape key to close
const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
        userMenuOpen.value = false;
        mobileOpen.value = false;
    }
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <header class="guest_navbar py-4 px-4 lg:px-16 border-b border-border sticky top-0 z-50 bg-background/95 backdrop-blur-sm transition-all duration-300">
        <nav class="flex items-center justify-between gap-4">
            <!-- Branding -->
            <div class="flex items-center gap-2">
                <Link href="/" class="font-bold">Albachuza</Link>
            </div>

            <!-- Desktop nav links -->
            <div class="links hidden lg:flex items-center gap-8">
                <Link href="/dashboard" v-if="user">Dashboard</Link>
                <Link href="/shop">Shop</Link>
                <Link href="/about">About</Link>
                <Link href="/contact">Contact</Link>
            </div>

            <!-- Right-side extras -->
            <div class="extras flex items-center gap-2 lg:gap-4">
                <!-- Theme toggle: always visible, even on mobile -->
                <button
                    @click="updateAppearance(appearance === 'light' ? 'dark' : 'light')"
                    class="toggle-theme p-2 rounded-md hover:bg-accent"
                    title="Toggle theme"
                >
                    <Sun v-if="appearance === 'light'" class="icon sun-icon w-5 h-5" />
                    <Moon v-else class="icon moon-icon w-5 h-5" />
                </button>

                <!-- Logged-in user menu -->
                <div v-if="user" ref="userMenuRef" class="relative">
                    <button
                        @click="toggleUserMenu"
                        class="flex items-center gap-2 p-1 pr-2 rounded-full hover:bg-accent transition-colors"
                        :aria-expanded="userMenuOpen"
                        aria-haspopup="true"
                    >
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                            class="w-8 h-8 rounded-full object-cover"
                        />
                        <span
                            v-else
                            class="w-8 h-8 rounded-full bg-foreground text-background flex items-center justify-center text-sm font-semibold"
                        >
                            {{ userInitial }}
                        </span>
                        <span class="hidden lg:inline text-sm font-medium">{{ user.name }}</span>
                        <ChevronDown
                            class="hidden lg:block w-4 h-4 transition-transform"
                            :class="{ 'rotate-180': userMenuOpen }"
                        />
                    </button>

                    <!-- Dropdown -->
                    <Transition
                        enter-active-class="transition ease-out duration-150"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-100"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 mt-2 w-56 rounded-md border border-border bg-background shadow-lg overflow-hidden z-50"
                        >
                            <div class="px-4 py-3 border-b border-border">
                                <p class="text-sm font-medium truncate">{{ user.name }}</p>
                                <p class="text-xs text-muted-foreground truncate">{{ user.email }}</p>
                            </div>

                            <div class="py-1">
                                <Link
                                    href="/dashboard"
                                    class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-accent"
                                    @click="userMenuOpen = false"
                                >
                                    <LayoutDashboard class="w-4 h-4" />
                                    Dashboard
                                </Link>
                                <Link
                                    href="/settings/profile"
                                    class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-accent"
                                    @click="userMenuOpen = false"
                                >
                                    <User class="w-4 h-4" />
                                    Profile
                                </Link>
                            </div>

                            <div class="border-t border-border py-1">
                                <button
                                    @click="logout"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-left hover:bg-accent text-red-600"
                                >
                                    <LogOut class="w-4 h-4" />
                                    Log out
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Guest login -->
                <div v-else class="auth_pages_links flex items-center gap-2">
                    <Link
                        href="/login"
                        class="px-3 py-1.5 bg-foreground text-background font-medium tracking-wide rounded-sm hover:opacity-90"
                    >
                        Login
                    </Link>
                </div>

                <button
                    class="lg:hidden p-2 -ml-2 rounded-md hover:bg-accent"
                    @click="mobileOpen = !mobileOpen"
                    :aria-expanded="mobileOpen"
                    aria-label="Toggle menu"
                >
                    <X v-if="mobileOpen" class="w-5 h-5" />
                    <Menu v-else class="w-5 h-5" />
                </button>
            </div>
        </nav>

        <!-- Mobile dropdown (nav links only) -->
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="mobileOpen"
                class="lg:hidden mt-4 pt-4 border-t border-border flex flex-col gap-1"
            >
                <Link
                    v-if="user"
                    href="/dashboard"
                    class="py-2 px-2 rounded-md hover:bg-accent"
                    @click="closeMobile"
                >
                    Dashboard
                </Link>
                <Link href="/"    class="py-2 px-2 rounded-md hover:bg-accent" @click="closeMobile">Home</Link>
                <Link href="/shop"    class="py-2 px-2 rounded-md hover:bg-accent" @click="closeMobile">Shop</Link>
                <Link href="/about"   class="py-2 px-2 rounded-md hover:bg-accent" @click="closeMobile">About</Link>
                <Link href="/contact" class="py-2 px-2 rounded-md hover:bg-accent" @click="closeMobile">Contact</Link>
            </div>
        </Transition>
    </header>
</template>