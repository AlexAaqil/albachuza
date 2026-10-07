<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { Sun, Moon, Menu, X, LogOut } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();
const page = usePage();

const user = computed(() => page.props.auth?.user);

// Mobile menu state
const isMobileMenuOpen = ref(false);

// Toggle mobile menu
const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

// Close mobile menu when a link is clicked
const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <header
        class="guest_navbar bg-accent text-accent-foreground sticky top-0 z-50 px-4 py-4 backdrop-blur-sm transition-all duration-300 lg:px-16"
    >
        <nav class="flex items-center justify-between">
            <!-- Branding - UNCHANGED -->
            <div class="branding">
                <Link href="/" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>
            </div>

            <!-- Desktop Navigation - Hidden on mobile, shown on lg -->
            <div class="links hidden items-center gap-8 lg:flex">
                <Link href="/dashboard" v-if="user">Dashboard</Link>
                <Link href="/catalogue">Catalogue</Link>
                <Link href="/contact">Contact</Link>
            </div>

            <!-- Desktop Extras - Hidden on mobile, shown on lg -->
            <div
                class="extras hidden items-center justify-center gap-4 lg:flex"
            >
                <button
                    @click="
                        updateAppearance(
                            appearance === 'light' ? 'dark' : 'light',
                        )
                    "
                    class="toggle-theme"
                    title="Toggle theme"
                >
                    <Sun
                        v-if="appearance === 'light'"
                        class="icon sun-icon h-5 w-5"
                    />
                    <Moon v-else class="icon moon-icon h-5 w-5" />
                </button>

                <div v-if="user" class="flex items-center gap-2">
                    <button
                        @click="logout"
                        class="flex items-center gap-2 rounded-sm bg-red-600 px-3 py-1.5 font-bold tracking-wide text-white transition-colors hover:bg-red-700"
                    >
                        <LogOut class="h-4 w-4" />
                        Logout
                    </button>
                </div>

                <div v-else class="auth_pages_links flex items-center gap-2">
                    <Link
                        href="/login"
                        class="bg-accent-foreground text-accent rounded-sm px-3 py-1.5 font-bold tracking-wide hover:opacity-90"
                        >Login</Link
                    >
                </div>
            </div>

            <!-- Mobile hamburger menu button -->
            <div class="flex items-center gap-3 lg:hidden">
                <!-- Theme toggle on mobile -->
                <button
                    @click="
                        updateAppearance(
                            appearance === 'light' ? 'dark' : 'light',
                        )
                    "
                    class="toggle-theme p-1"
                    title="Toggle theme"
                >
                    <Sun
                        v-if="appearance === 'light'"
                        class="icon sun-icon h-5 w-5"
                    />
                    <Moon v-else class="icon moon-icon h-5 w-5" />
                </button>

                <!-- Hamburger button -->
                <button
                    @click="toggleMobileMenu"
                    class="p-1 transition-opacity hover:opacity-70"
                    aria-label="Toggle menu"
                >
                    <Menu v-if="!isMobileMenuOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>
            </div>
        </nav>

        <!-- Mobile Menu Dropdown -->
        <div
            v-if="isMobileMenuOpen"
            class="border-accent-foreground/10 mt-4 border-t pt-4 lg:hidden"
        >
            <div class="flex flex-col space-y-3">
                <!-- Navigation Links -->
                <Link
                    href="/dashboard"
                    v-if="user"
                    class="hover:bg-accent-foreground/5 rounded-md px-2 py-2 transition-colors"
                    @click="closeMobileMenu"
                >
                    Dashboard
                </Link>
                <Link
                    href="/catalogue"
                    class="hover:bg-accent-foreground/5 rounded-md px-2 py-2 transition-colors"
                    @click="closeMobileMenu"
                >
                    Catalogue
                </Link>
                <Link
                    href="/contact"
                    class="hover:bg-accent-foreground/5 rounded-md px-2 py-2 transition-colors"
                    @click="closeMobileMenu"
                >
                    Contact
                </Link>

                <!-- Divider -->
                <div class="border-accent-foreground/10 my-1 border-t"></div>

                <div v-if="!user" class="flex flex-col space-y-2">
                    <Link
                        href="/login"
                        class="bg-accent-foreground text-accent rounded-sm px-4 py-2 text-center font-bold tracking-wide hover:opacity-90"
                        @click="closeMobileMenu"
                    >
                        Login
                    </Link>
                </div>

                <div v-else class="flex flex-col space-y-2">
                    <button
                        @click="logout"
                        class="flex items-center justify-center gap-2 rounded-sm bg-red-600 px-4 py-2 font-bold tracking-wide text-white transition-colors hover:bg-red-700"
                    >
                        <LogOut class="h-4 w-4" />
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
/* Mobile menu animation */
.guest_navbar {
    transition: all 0.3s ease;
}

/* Smooth mobile menu enter/exit */
.mobile-menu-enter-active,
.mobile-menu-leave-active {
    transition: all 0.3s ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Touch-friendly tap targets on mobile */
@media (max-width: 1023px) {
    .guest_navbar nav button,
    .guest_navbar nav a {
        min-height: 44px;
        min-width: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
}

/* Prevent body scroll when mobile menu is open */
body.menu-open {
    overflow: hidden;
}
</style>
