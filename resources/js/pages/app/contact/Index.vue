<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2, X, Check } from '@lucide/vue';
import { ref, computed } from 'vue';
import AppPageHeader from '@/components/custom/AppPageHeader.vue';
import DeleteConfirmationDialog from '@/components/custom/DeleteConfirmation.vue';
import Pagination from '@/components/custom/Pagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import contactMessageRoutes from '@/routes/contact-messages';

interface ContactMessage {
    id: number;
    uuid: string;
    name: string;
    phone_number: string;
    message: string;
    is_read: boolean;
    is_resolved: boolean;
}

interface Props {
    contact_messages: {
        data: ContactMessage[];
        links: any[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            links: any[];
        };
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const status_filter = ref(props.filters?.status || '');

const search = ref(props.filters?.search || '');
const handleSearch = (value: string) => {
    router.get(contactMessageRoutes.index().url, {
        search: value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const getDisplayRange = computed(() => {
    const { current_page, per_page, total } = props.contact_messages.meta;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);

    return { start, end, total };
});

const hasActiveFilters = computed(() => 
    !!(search.value || status_filter.value)
);
</script>

<template>
    <Head title="Callback Messages" />
    
    <AppPageHeader
        resourceName="Messages"
        v-model="search"
        search-placeholder="Search by name..."
        @search="handleSearch"
    />

    <div class="table-wrapper">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="id">#</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Phone</TableHead>
                    <TableHead>Message</TableHead>
                    <TableHead>Resolved</TableHead>
                    <TableHead class="actions">Actions</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(callback_message, index) in contact_messages.data" :key="callback_message.id" :class="callback_message.is_read ? '' : 'font-bold'">
                    <TableCell class="id">{{ (contact_messages.meta.current_page - 1) * contact_messages.meta.per_page + index + 1 }}</TableCell>
                    <TableCell>{{ callback_message.name }}</TableCell>
                    <TableCell>{{ callback_message.phone_number }}</TableCell>
                    <TableCell>{{ callback_message.message }}</TableCell>
                    <TableCell>
                        <Check v-if="callback_message.is_resolved" class="text-green-800" />
                        <X v-else class="text-red-600" />
                    </TableCell>
                    <TableCell class="actions">
                        <div class="actions-wrapper">
                            <Link :href="contactMessageRoutes.edit(callback_message.uuid).url" class="action edit">
                                <Pencil />
                            </Link>
                            <span class="divider">|</span>
                            <DeleteConfirmationDialog 
                                :url="contactMessageRoutes.destroy(callback_message.uuid).url" 
                                title="Delete Callback?" 
                                description="This callback message will be deleted permanently!" 
                                confirm-text="Delete Callback"
                            >
                                <template #trigger>
                                    <button class="action delete">
                                        <Trash2 />
                                    </button>
                                </template>
                            </DeleteConfirmationDialog>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="contact_messages.data.length === 0">
                    <TableCell colspan="6" class="blank-table-row">
                        No contact_messages found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <Pagination :meta="contact_messages.meta" />

    <div class="table-results-summary">
        <p>
            Showing {{ getDisplayRange.start }} to {{ getDisplayRange.end }}
            of {{ getDisplayRange.total }} products
        </p>
        <p v-if="hasActiveFilters" class="filtered-results">
            Filtered results
        </p>
    </div>
</template>