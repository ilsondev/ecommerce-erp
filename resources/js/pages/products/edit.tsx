import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import ProductForm from '@/components/product-form';
import ProductImages from '@/components/product-images';
import ProductStock from '@/components/product-stock';
import { Button } from '@/components/ui/button';
import { destroy, edit, index } from '@/routes/products';
import type { Product, ProductImage, StockMovement } from '@/types/products';

type Props = {
    product: Product;
    images: ProductImage[];
    movements: StockMovement[];
    canManage: boolean;
    imageStorageConfigured: boolean;
};

export default function EditProduct({
    product,
    images,
    movements,
    canManage,
    imageStorageConfigured,
}: Props) {
    const { currentTenant } = usePage().props;
    return (
        <>
            <Head title={product.name} />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
                <div className="flex items-center justify-between gap-4">
                    <Button variant="ghost" asChild>
                        <Link href={index(currentTenant?.slug)}>
                            <ArrowLeft /> Produtos
                        </Link>
                    </Button>
                    {canManage && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.delete(
                                    destroy.url({
                                        current_tenant: currentTenant?.slug,
                                        product: product.id,
                                    }),
                                )
                            }
                        >
                            <Archive /> Arquivar
                        </Button>
                    )}
                </div>
                <Heading
                    title={product.name}
                    description={`SKU ${product.sku}`}
                />
                <div className="grid items-start gap-6 lg:grid-cols-[3fr_2fr]">
                    <section className="space-y-5 rounded-xl border bg-card p-6">
                        <h2 className="font-semibold">Dados do produto</h2>
                        <ProductForm product={product} disabled={!canManage} />
                    </section>
                    <ProductImages
                        productId={product.id}
                        images={images}
                        canManage={canManage}
                        storageConfigured={imageStorageConfigured}
                    />
                </div>
                <ProductStock
                    productId={product.id}
                    quantity={product.stock_quantity}
                    movements={movements}
                    canManage={canManage}
                />
            </div>
        </>
    );
}

EditProduct.layout = (props: {
    product: Product;
    currentTenant?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        { title: 'Produtos', href: index(props.currentTenant?.slug) },
        {
            title: props.product.name,
            href: edit({
                current_tenant: props.currentTenant?.slug,
                product: props.product.id,
            }),
        },
    ],
});
