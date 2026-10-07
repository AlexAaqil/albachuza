// composables/useAppConfig.ts
import { computed } from 'vue';

interface AppConfig {
    brand_name: string;
    brand_description: string;
    location: {
        city: string;
        address: string;
        shop: string;
    };
    contacts: {
        phone: string;
    };
    socials: {
        tiktok: string;
        whatsapp: string;
    };
}

// Just hardcode everything here! ✅
const config: AppConfig = {
    brand_name: 'Albachuza',
    brand_description: 'Best new & mitumba shoes',
    location: {
        city: 'Kiambu, Ruii',
        address: 'Opposite KIBCO College',
        shop: 'Shop 3',
    },
    contacts: {
        phone: '+254 769 696 309',
    },
    socials: {
        tiktok: 'https://www.tiktok.com/@alba.chuza',
        whatsapp: 'https://wa.me/254769696309',
    },
};

export function useAppConfig() {
    return {
        brandName: computed(() => config.brand_name),
        brandDescription: computed(() => config.brand_description),
        location: computed(() => config.location),
        contacts: computed(() => config.contacts),
        socials: computed(() => config.socials),
    };
}