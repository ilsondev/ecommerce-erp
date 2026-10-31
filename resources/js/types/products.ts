export type Product = {
    id: number;
    sku: string;
    name: string;
    description: string | null;
    barcode: string | null;
    brand: string | null;
    price: string;
    stock_quantity: number;
    deleted_at?: string | null;
};

export type ProductImage = {
    id: number;
    name: string;
    status: 'pending' | 'processing' | 'ready' | 'failed';
    error: string | null;
    url: string | null;
};

export type StockMovement = {
    id: number;
    quantity: number;
    balance_after: number;
    reason: string;
    user: string;
    created_at: string;
};

export type PaginatedProducts = {
    data: Product[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
