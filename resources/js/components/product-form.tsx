import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/products';
import type { Product } from '@/types/products';

export default function ProductForm({
    product,
    disabled = false,
}: {
    product?: Product;
    disabled?: boolean;
}) {
    const { currentTenant } = usePage().props;
    const form = useForm({
        sku: product?.sku ?? '',
        name: product?.name ?? '',
        description: product?.description ?? '',
        brand: product?.brand ?? '',
        barcode: product?.barcode ?? '',
        price: product?.price ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (!currentTenant) return;
        if (product) {
            form.patch(
                update.url({
                    current_tenant: currentTenant.slug,
                    product: product.id,
                }),
                { preserveScroll: true },
            );
        } else {
            form.post(store.url(currentTenant.slug));
        }
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            <fieldset
                disabled={disabled || form.processing}
                className="grid gap-5 sm:grid-cols-2"
            >
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="name">Nome do produto</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                        maxLength={255}
                        aria-invalid={!!form.errors.name}
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sku">SKU</Label>
                    <Input
                        id="sku"
                        value={form.data.sku}
                        onChange={(event) =>
                            form.setData('sku', event.target.value)
                        }
                        required
                        maxLength={100}
                        aria-invalid={!!form.errors.sku}
                    />
                    <p className="text-xs text-muted-foreground">
                        Código único do produto neste tenant.
                    </p>
                    <InputError message={form.errors.sku} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="price">Preço de venda (R$)</Label>
                    <Input
                        id="price"
                        type="number"
                        min="0"
                        max="9999999999.99"
                        step="0.01"
                        value={form.data.price}
                        onChange={(event) =>
                            form.setData('price', event.target.value)
                        }
                        required
                        aria-invalid={!!form.errors.price}
                    />
                    <InputError message={form.errors.price} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="brand">Marca</Label>
                    <Input
                        id="brand"
                        value={form.data.brand}
                        onChange={(event) =>
                            form.setData('brand', event.target.value)
                        }
                        maxLength={100}
                    />
                    <InputError message={form.errors.brand} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="barcode">Código de barras</Label>
                    <Input
                        id="barcode"
                        value={form.data.barcode}
                        onChange={(event) =>
                            form.setData('barcode', event.target.value)
                        }
                        inputMode="numeric"
                        maxLength={14}
                    />
                    <InputError message={form.errors.barcode} />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="description">Descrição</Label>
                    <textarea
                        id="description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        maxLength={10000}
                        rows={5}
                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    <InputError message={form.errors.description} />
                </div>
                {!disabled && (
                    <Button
                        type="submit"
                        className="sm:col-span-2 sm:w-fit"
                        disabled={form.processing}
                    >
                        {form.processing
                            ? 'Salvando…'
                            : product
                              ? 'Salvar alterações'
                              : 'Cadastrar produto'}
                    </Button>
                )}
            </fieldset>
        </form>
    );
}
