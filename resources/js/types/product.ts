export interface ProductImage {
    /** Full size (product page). */
    url: string;
    /** Medium size (storefront grid and carousel). */
    card_url?: string;
    /** Small size (thumbnails). */
    thumb_url?: string;
    alt: string;
}

/** One photo in the admin image picker: either already saved (id) or newly chosen (file). */
export interface ProductImageItem {
    id?: number;
    file?: File;
    preview: string;
}

export interface Product {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    category_name: string;
    description: string;
    cost_price: number;
    price: number;
    compare_price: number | null;
    stock: number;
    sku: string;
    barcode: string;
    size: string;
    category: string;
    tags: string[];
    thumbnail_url: string;
    card_image_url?: string;
    images: ProductImage[];
    rating: number;
    reviews_count: number;
    is_featured: boolean;
    is_new: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    track_inventory: boolean;
    current_stock: number;
    low_stock_threshold: number;
    stock_status: string;
    stock_badge_class: string;
}