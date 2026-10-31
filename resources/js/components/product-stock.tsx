import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/products/stock-movements';
import type { StockMovement } from '@/types/products';

export default function ProductStock({
    productId,
    quantity,
    movements,
    canManage,
}: {
    productId: number;
    quantity: number;
    movements: StockMovement[];
    canManage: boolean;
}) {
    const { currentTenant } = usePage().props;
    const form = useForm({
        quantity: '',
        reason: '',
        idempotency_key: crypto.randomUUID(),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(
            store.url({
                current_tenant: currentTenant?.slug,
                product: productId,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset('quantity', 'reason');
                    form.setData('idempotency_key', crypto.randomUUID());
                },
            },
        );
    }

    return (
        <section className="space-y-6 rounded-xl border bg-card p-6">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h2 className="font-semibold">Estoque</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Saldo disponível e histórico de movimentações.
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-3xl font-semibold tabular-nums">
                        {quantity}
                    </p>
                    <p className="text-xs text-muted-foreground">unidades</p>
                </div>
            </div>
            {canManage && (
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-lg bg-muted/30 p-4 sm:grid-cols-[1fr_2fr]"
                >
                    <div className="space-y-2">
                        <Label htmlFor="quantity">Quantidade</Label>
                        <Input
                            id="quantity"
                            type="number"
                            step="1"
                            min="-1000000"
                            max="1000000"
                            placeholder="Ex.: 10 ou -2"
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData('quantity', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.quantity} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="reason">Motivo</Label>
                        <Input
                            id="reason"
                            placeholder="Ex.: compra, venda ou ajuste de inventário"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            maxLength={255}
                            required
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <p className="text-xs text-muted-foreground sm:col-span-2">
                        Use valores positivos para entradas e negativos para
                        saídas.
                    </p>
                    <InputError
                        message={form.errors.idempotency_key}
                        className="sm:col-span-2"
                    />
                    <Button
                        type="submit"
                        className="w-fit"
                        disabled={form.processing}
                    >
                        {form.processing
                            ? 'Registrando…'
                            : 'Registrar movimentação'}
                    </Button>
                </form>
            )}
            {movements.length === 0 ? (
                <p className="py-4 text-sm text-muted-foreground">
                    Nenhuma movimentação registrada.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <caption className="pb-3 text-left text-xs text-muted-foreground">
                            Últimas 50 movimentações
                        </caption>
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="py-3 pr-4 font-medium">
                                    Data / responsável
                                </th>
                                <th className="py-3 pr-4 font-medium">
                                    Motivo
                                </th>
                                <th className="py-3 pr-4 text-right font-medium">
                                    Variação
                                </th>
                                <th className="py-3 text-right font-medium">
                                    Saldo
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {movements.map((movement) => (
                                <tr key={movement.id}>
                                    <td className="py-3 pr-4">
                                        <p className="whitespace-nowrap">
                                            {new Date(
                                                movement.created_at,
                                            ).toLocaleString('pt-BR')}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {movement.user}
                                        </p>
                                    </td>
                                    <td className="py-3 pr-4">
                                        {movement.reason}
                                    </td>
                                    <td
                                        className={`py-3 pr-4 text-right font-medium tabular-nums ${movement.quantity > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'}`}
                                    >
                                        {movement.quantity > 0 ? '+' : ''}
                                        {movement.quantity}
                                    </td>
                                    <td className="py-3 text-right tabular-nums">
                                        {movement.balance_after}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
