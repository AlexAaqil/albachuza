<script setup lang="ts">
import { ChevronLeft, ChevronRight, ImagePlus, X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { prepareImageForUpload } from '@/lib/prepareImage';
import type { ProductImageItem } from '@/types/product';

const props = withDefaults(
    defineProps<{
        /** Maximum number of photos (keep in sync with config/product_images.php). */
        max?: number;
        /** Largest file the server accepts, in bytes (config/product_images.php: max_upload_kb). */
        maxBytes?: number;
    }>(),
    {
        max: 5,
        maxBytes: 10 * 1024 * 1024,
    },
);

const emit = defineEmits<{
    /** A photo that was already saved on the server was taken out. */
    remove: [item: ProductImageItem];
}>();

const items = defineModel<ProductImageItem[]>({ required: true });

const preparing = ref(false);
const message = ref('');

const isFull = () => items.value.length >= props.max;

const handleChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).filter((file) => file.type.startsWith('image/'));

    input.value = '';
    message.value = '';

    if (!files.length) {
        return;
    }

    const room = props.max - items.value.length;

    if (files.length > room) {
        message.value = `A product can have at most ${props.max} images. ${files.length - room} were skipped.`;
    }

    preparing.value = true;

    try {
        // One at a time: decoding several big photos together can run a phone out of memory
        for (const original of files.slice(0, Math.max(room, 0))) {
            const file = await prepareImageForUpload(original);

            if (file.size > props.maxBytes) {
                message.value = `${original.name} is too large to upload.`;
                continue;
            }

            items.value = [...items.value, { file, preview: URL.createObjectURL(file) }];
        }
    } finally {
        preparing.value = false;
    }
};

const move = (index: number, direction: -1 | 1) => {
    const target = index + direction;

    if (target < 0 || target >= items.value.length) {
        return;
    }

    const next = [...items.value];
    [next[index], next[target]] = [next[target], next[index]];
    items.value = next;
};

const remove = (index: number) => {
    const item = items.value[index];

    if (item.preview.startsWith('blob:')) {
        URL.revokeObjectURL(item.preview);
    }

    if (item.id !== undefined) {
        emit('remove', item);
    }

    items.value = items.value.filter((_, i) => i !== index);
};

onBeforeUnmount(() => {
    items.value.forEach((item) => {
        if (item.preview.startsWith('blob:')) {
            URL.revokeObjectURL(item.preview);
        }
    });
});
</script>

<template>
    <div>
        <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
            <div
                v-for="(item, index) in items"
                :key="item.id ?? item.preview"
                class="relative aspect-square border rounded-xl overflow-hidden bg-white"
            >
                <!-- object-contain: show the whole photo, nothing cut off -->
                <img :src="item.preview" alt="" class="w-full h-full object-contain" />

                <button
                    type="button"
                    @click="remove(index)"
                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1"
                    title="Remove photo"
                >
                    <X class="w-3 h-3" />
                </button>

                <span
                    v-if="index === 0"
                    class="absolute bottom-1 left-1 text-xs bg-black text-white px-1 rounded"
                >
                    Primary
                </span>

                <div class="absolute bottom-1 right-1 flex gap-1">
                    <button
                        v-if="index > 0"
                        type="button"
                        @click="move(index, -1)"
                        class="bg-black/60 text-white rounded-full p-1"
                        title="Move earlier"
                    >
                        <ChevronLeft class="w-3 h-3" />
                    </button>
                    <button
                        v-if="index < items.length - 1"
                        type="button"
                        @click="move(index, 1)"
                        class="bg-black/60 text-white rounded-full p-1"
                        title="Move later"
                    >
                        <ChevronRight class="w-3 h-3" />
                    </button>
                </div>
            </div>

            <div
                v-if="preparing"
                class="flex flex-col items-center justify-center border rounded-xl aspect-square text-xs text-gray-500"
            >
                <Spinner />
                <span class="mt-1">Preparing…</span>
            </div>

            <label
                v-if="!isFull() && !preparing"
                class="flex flex-col items-center justify-center border-2 border-dashed rounded-xl aspect-square cursor-pointer hover:bg-gray-50"
            >
                <ImagePlus class="w-8 h-8 text-gray-400 mb-1" />
                <span class="text-xs text-gray-500">Add photo</span>
                <input type="file" multiple accept="image/*" class="hidden" @change="handleChange" />
            </label>
        </div>

        <p class="text-xs text-gray-400 mt-2">
            Up to {{ max }} photos. The first one is the main photo. Photos are shrunk automatically before
            uploading and are never cropped.
        </p>

        <InputError :message="message" />
    </div>
</template>