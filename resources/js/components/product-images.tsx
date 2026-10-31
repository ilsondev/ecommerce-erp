import { router, useHttp, usePage, usePoll } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { complete, destroy, store } from '@/routes/products/images';
import type { ProductImage } from '@/types/products';

type UploadData = { name: string; mime_type: string; size: number };
type UploadResponse = {
    id: number;
    url: string;
    headers: Record<string, string | string[]>;
};

function ProcessingImages() {
    usePoll(5000, { only: ['images'] });
    return (
        <p
            role="status"
            className="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <LoaderCircle className="size-4 animate-spin" /> Processando
            imagens. Elas aparecerão automaticamente.
        </p>
    );
}

export default function ProductImages({
    productId,
    images,
    canManage,
    storageConfigured,
}: {
    productId: number;
    images: ProductImage[];
    canManage: boolean;
    storageConfigured: boolean;
}) {
    const { currentTenant } = usePage().props;
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState('');
    const upload = useHttp<UploadData, UploadResponse>({
        name: '',
        mime_type: '',
        size: 0,
    });

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const element = event.currentTarget;
        if (!file) return;
        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
            file.size > 5242880
        ) {
            setError('Escolha uma imagem JPEG, PNG ou WebP com até 5 MB.');
            return;
        }
        setUploading(true);
        setError('');
        upload.clearErrors();
        try {
            upload.transform(() => ({
                name: file.name,
                mime_type: file.type,
                size: file.size,
            }));
            const response = await upload.post(
                store.url({
                    current_tenant: currentTenant?.slug,
                    product: productId,
                }),
            );
            const headers = Object.fromEntries(
                Object.entries(response.headers)
                    .filter(([name]) => name.toLowerCase() !== 'host')
                    .map(([name, value]) => [
                        name,
                        Array.isArray(value) ? value.join(', ') : value,
                    ]),
            );
            const result = await fetch(response.url, {
                method: 'PUT',
                headers,
                body: file,
            });
            if (!result.ok)
                throw new Error(
                    'Não foi possível enviar a imagem ao armazenamento. Tente novamente.',
                );
            router.post(
                complete.url({
                    current_tenant: currentTenant?.slug,
                    product: productId,
                    image: response.id,
                }),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setFile(null);
                        element.reset();
                    },
                    onError: () =>
                        setError(
                            'A imagem foi enviada, mas não foi possível iniciar o processamento. Remova-a e tente novamente.',
                        ),
                    onFinish: () => setUploading(false),
                },
            );
        } catch {
            router.reload({ only: ['images'] });
            setError(
                'Não foi possível enviar a imagem. Verifique a conexão e a configuração de acesso ao armazenamento.',
            );
            setUploading(false);
        }
    }

    return (
        <section className="space-y-5 rounded-xl border bg-card p-6">
            <div>
                <h2 className="font-semibold">Imagens</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Até 10 imagens por produto. JPEG, PNG ou WebP, com até 5 MB
                    cada.
                </p>
            </div>
            {!storageConfigured && (
                <p
                    role="status"
                    className="rounded-md bg-muted p-3 text-sm text-muted-foreground"
                >
                    O armazenamento de imagens ainda não foi configurado.
                    Solicite a configuração ao administrador.
                </p>
            )}
            {images.some((image) => image.status === 'processing') && (
                <ProcessingImages />
            )}
            {images.length === 0 && (
                <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed py-8 text-sm text-muted-foreground">
                    <ImagePlus className="size-8" /> Nenhuma imagem adicionada.
                </div>
            )}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                {images.map((image) => (
                    <div
                        key={image.id}
                        className="overflow-hidden rounded-lg border"
                    >
                        {image.url ? (
                            <img
                                src={image.url}
                                alt={image.name}
                                className="aspect-square w-full bg-muted object-contain"
                                loading="lazy"
                            />
                        ) : (
                            <div className="flex aspect-square items-center justify-center bg-muted p-3 text-center text-xs text-muted-foreground">
                                {image.status === 'failed'
                                    ? image.error
                                    : image.status === 'processing'
                                      ? 'Processando…'
                                      : 'Aguardando envio'}
                            </div>
                        )}
                        <div className="flex items-center justify-between gap-2 p-2">
                            <span
                                className="truncate text-xs"
                                title={image.name}
                            >
                                {image.name}
                            </span>
                            {canManage && (
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={`Remover ${image.name}`}
                                    onClick={() =>
                                        router.delete(
                                            destroy.url({
                                                current_tenant:
                                                    currentTenant?.slug,
                                                product: productId,
                                                image: image.id,
                                            }),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
            {canManage && storageConfigured && images.length < 10 && (
                <form
                    onSubmit={(event) => {
                        void submit(event);
                    }}
                    className="space-y-3"
                >
                    <Label htmlFor="product-image">Adicionar imagem</Label>
                    <Input
                        id="product-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        disabled={uploading}
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                    />
                    <InputError
                        message={
                            Object.values(upload.errors).flat().join(' ') ||
                            error
                        }
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={uploading || !file}
                    >
                        {uploading ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <ImagePlus />
                        )}
                        {uploading ? 'Enviando…' : 'Enviar imagem'}
                    </Button>
                </form>
            )}
        </section>
    );
}
