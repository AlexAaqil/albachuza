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
        whatsapp_phone: string;
    };
    socials: {
        tiktok_url: string;
        whatsapp_url: string;
        site_url: string;
    };
}

const WHATSAPP_PHONE = '254769696309';

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
        whatsapp_phone: WHATSAPP_PHONE,
    },
    socials: {
        tiktok_url: 'https://www.tiktok.com/@alba.chuza',
        whatsapp_url: `https://wa.me/${WHATSAPP_PHONE}`,
        site_url: 'https://albachuza.alsuwa.com',
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