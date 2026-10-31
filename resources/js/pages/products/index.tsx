import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArchiveRestore,
    ArrowRight,
    Package,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index, restore } from '@/routes/products';
import type { PaginatedProducts } from '@/types/products';

export default function ProductsIndex({
    products,
    filters,
    canManage,
}: {
    products: PaginatedProducts;
    filters: { search: string; archived: boolean };
    canManage: boolean;
}) {
    const { currentTenant } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const currency = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(
            index.url(currentTenant?.slug),
            { search, archived: filters.archived ? 1 : 0 },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Produtos" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Produtos"
                        description="Seu catálogo, suas imagens e o estoque da sua operação."
                    />
                    {canManage && (
                        <Button asChild>
                            <Link href={create(currentTenant?.slug)}>
                                <Plus /> Novo produto
                            </Link>
                        </Button>
                    )}
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <form
                        onSubmit={submit}
                        className="flex w-full max-w-md gap-2"
                    >
                        <Input
                            aria-label="Buscar por nome ou SKU"
                            placeholder="Buscar por nome ou SKU…"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            maxLength={100}
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            aria-label="Buscar"
                        >
                            <Search />
                        </Button>
                    </form>
                    <div className="flex gap-2">
                        <Button
                            variant={!filters.archived ? 'secondary' : 'ghost'}
                            asChild
                        >
                            <Link
                                href={index(currentTenant?.slug, {
                                    query: { search: filters.search },
                                })}
                            >
                                Ativos
                            </Link>
                        </Button>
                        <Button
                            variant={filters.archived ? 'secondary' : 'ghost'}
                            asChild
                        >
                            <Link
                                href={index(currentTenant?.slug, {
                                    query: {
                                        archived: 1,
                                        search: filters.search,
                                    },
                                })}
                            >
                                Arquivados
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="overflow-hidden rounded-xl border bg-card">
                    {products.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-20 text-center">
                            <Package className="size-10 text-muted-foreground" />
                            <h2 className="text-lg font-medium">
                                {filters.search
                                    ? 'Nenhum produto encontrado'
                                    : filters.archived
                                      ? 'Nenhum produto arquivado'
                                      : 'Seu catálogo começa aqui'}
                            </h2>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                {filters.search
                                    ? 'Tente outro nome ou SKU.'
                                    : 'Cadastre seus produtos para organizar as imagens e controlar entradas e saídas de estoque.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-4 font-medium">
                                            Produto
                                        </th>
                                        <th className="px-5 py-4 font-medium">
                                            SKU
                                        </th>
                                        <th className="px-5 py-4 font-medium">
                                            Preço
                                        </th>
                                        <th className="px-5 py-4 font-medium">
                                            Estoque
                                        </th>
                                        <th className="px-5 py-4">
                                            <span className="sr-only">
                                                Ações
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {products.data.map((product) => (
                                        <tr
                                            key={product.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-5 py-4">
                                                <div className="font-medium">
                                                    {product.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {product.brand}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 font-mono text-xs">
                                                {product.sku}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                {currency.format(
                                                    Number(product.price),
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={
                                                        product.stock_quantity ===
                                                        0
                                                            ? 'text-amber-700 dark:text-amber-400'
                                                            : 'font-medium'
                                                    }
                                                >
                                                    {product.stock_quantity}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                {filters.archived ? (
                                                    canManage && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    restore.url(
                                                                        {
                                                                            current_tenant:
                                                                                currentTenant?.slug,
                                                                            product:
                                                                                product.id,
                                                                        },
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <ArchiveRestore />{' '}
                                                            Restaurar
                                                        </Button>
                                                    )
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        asChild
                                                    >
                                                        <Link
                                                            aria-label={`Abrir ${product.name}`}
                                                            href={edit({
                                                                current_tenant:
                                                                    currentTenant?.slug,
                                                                product:
                                                                    product.id,
                                                            })}
                                                        >
                                                            <ArrowRight />
                                                        </Link>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                <div className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
                    <p>
                        {products.total}{' '}
                        {products.total === 1 ? 'produto' : 'produtos'} · Página{' '}
                        {products.current_page} de {products.last_page}
                    </p>
                    <div className="flex gap-2">
                        {products.prev_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={products.prev_page_url}>
                                    Anterior
                                </Link>
                            </Button>
                        )}
                        {products.next_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={products.next_page_url}>
                                    Próxima
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ProductsIndex.layout = (props: {
    currentTenant?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        { title: 'Produtos', href: index(props.currentTenant?.slug) },
    ],
});
