import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import ProductForm from '@/components/product-form';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/products';

export default function CreateProduct() {
    const { currentTenant } = usePage().props;
    return (
        <>
            <Head title="Novo produto" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
                <Button variant="ghost" asChild className="w-fit">
                    <Link href={index(currentTenant?.slug)}>
                        <ArrowLeft /> Produtos
                    </Link>
                </Button>
                <Heading
                    title="Novo produto"
                    description="Cadastre seu produto. Depois, adicione imagens e registre o estoque inicial."
                />
                <div className="rounded-xl border bg-card p-6">
                    <ProductForm />
                </div>
            </div>
        </>
    );
}

CreateProduct.layout = (props: {
    currentTenant?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        { title: 'Produtos', href: index(props.currentTenant?.slug) },
        { title: 'Novo produto', href: '' },
    ],
});
