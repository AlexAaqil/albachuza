import { onBeforeUnmount, onMounted, type Ref } from 'vue';

export function useClickOutside(
    elRef: Ref<HTMLElement | null>,
    handler: () => void
) {
    const listener = (e: MouseEvent) => {
        const el = elRef.value;
        if (!el || el.contains(e.target as Node)) return;
        handler();
    };

    onMounted(() => document.addEventListener('mousedown', listener));
    onBeforeUnmount(() => document.removeEventListener('mousedown', listener));
}