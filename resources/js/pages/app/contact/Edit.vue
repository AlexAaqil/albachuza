<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CheckCircle, Circle } from '@lucide/vue';
import { ref, computed } from 'vue';
// import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import contactMessageRoutes from '@/routes/contact-messages';

interface ContactMessage {
    id: number;
    uuid: string;
    name: string;
    phone_number: string;
    message: string;
    is_read: boolean;
    is_resolved: boolean;
    created_at_time_formatted: string;
    created_at_date_formatted: string;
}

const props = defineProps<{
    contact_message: {
        data: ContactMessage;
    };
}>();

// Local reactive state for the resolved status
const isResolved = ref(props.contact_message.data.is_resolved);
const isToggling = ref(false);

// Computed property for the button text
const resolvedButtonText = computed(() => {
    return isResolved.value ? 'Resolved ✓' : 'Mark as Resolved';
});

// Toggle resolved status
const toggleResolved = () => {
    if (isToggling.value) {
        return;
    }
    
    isToggling.value = true;
    
    router.patch(
        contactMessageRoutes.toggleResolved(props.contact_message.data.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                // Update the local state
                isResolved.value = !isResolved.value;
                isToggling.value = false;
            },
            onError: () => {
                isToggling.value = false;
            }
        }
    );
};
</script>

<template>
    <div class="contact-message-wrapper mt-12 lg:w-[50dvw] lg:mx-auto">
        <div class="container-fluid">
            <div class="contact-message p-4 rounded-lg border border-border">
                <div class="header border-b border-border py-4 flex justify-between items-start">
                    <div>
                        <p class="font-bold">{{ contact_message.data.name }}</p>
                        <p class="text-sm">{{ contact_message.data.phone_number }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="[
                            'px-2 py-1 rounded-full text-xs font-medium',
                            isResolved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                        ]">
                            {{ isResolved ? 'Resolved' : 'Pending' }}
                        </span>
                    </div>
                </div>

                <div class="content py-4 space-y-4">
                    <div class="message-container flex">
                        <p class="message grid bg-blue-100 text-black p-4 rounded-lg w-full lg:w-[65%]">
                            <span>{{ contact_message.data.message }}</span>
                            <span class="text-xs mt-1 text-gray-600">
                                {{ contact_message.data.created_at_time_formatted }}
                            </span>
                        </p>
                    </div>
                    <p class="text-xs text-gray-500">
                        {{ contact_message.data.created_at_date_formatted }}
                    </p>
                </div>

                <div class="footer border-t border-border pt-4 mt-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Button
                                @click="toggleResolved"
                                :disabled="isToggling"
                                :variant="isResolved ? 'outline' : 'default'"
                                class="min-w-35"
                            >
                                <Spinner v-if="isToggling" class="mr-2 h-4 w-4 animate-spin" />
                                <CheckCircle v-else-if="isResolved" class="mr-2 h-4 w-4" />
                                <Circle v-else class="mr-2 h-4 w-4" />
                                {{ isToggling ? 'Updating...' : resolvedButtonText }}
                            </Button>
                        </div>

                        <Link :href=contactMessageRoutes.index().url>Back to Callbacks</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.contact-message {
    transition: border-color 0.3s ease;
}
</style>