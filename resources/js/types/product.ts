export interface ProductImage {
    url: string;
    alt: string;
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
    category: string;
    tags: string[];
    thumbnail_url: string;
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