import ModuleLayout from '@/Layouts/ModuleLayout';
import CrudIndex from '@/Components/CrudIndex';
import { formatDate, formatMoney, RowActions, StatusBadge } from '@/Components/FormHelpers';
import { router, usePage } from '@inertiajs/react';

export default function Index({ invoices, filters }) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const canCreate = permissions.includes('invoices.create');
    const canUpdate = permissions.includes('invoices.update');
    const canDelete = permissions.includes('invoices.delete');

    return (
        <ModuleLayout title="Invoices">
            <CrudIndex
                title="Invoices"
                description="Create and track client invoices."
                createHref={route('invoices.create')}
                createLabel="Add invoice"
                canCreate={canCreate}
                searchValue={filters.search}
                searchRoute={route('invoices.index')}
                meta={invoices}
                rows={invoices.data}
                columns={[
                    { key: 'invoice_number', label: 'Invoice #' },
                    {
                        key: 'client',
                        label: 'Client',
                        render: (row) => row.client?.company_name ?? '—',
                    },
                    {
                        key: 'invoice_date',
                        label: 'Date',
                        render: (row) => formatDate(row.invoice_date),
                    },
                    {
                        key: 'due_date',
                        label: 'Due',
                        render: (row) => formatDate(row.due_date),
                    },
                    {
                        key: 'amount',
                        label: 'Amount',
                        render: (row) => formatMoney(row.amount),
                    },
                    {
                        key: 'paid_amount',
                        label: 'Paid',
                        render: (row) => formatMoney(row.paid_amount),
                    },
                    {
                        key: 'payment_status',
                        label: 'Status',
                        render: (row) => <StatusBadge value={row.payment_status} />,
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        render: (row) => (
                            <div className="flex items-center gap-2">
                                <a
                                    href={route('invoices.pdf', row.id)}
                                    className="rounded-xl bg-leaf/10 px-3 py-1.5 text-xs font-semibold text-leaf transition hover:bg-leaf hover:text-white"
                                >
                                    PDF
                                </a>
                                <RowActions
                                    canUpdate={canUpdate}
                                    canDelete={canDelete}
                                    editHref={route('invoices.edit', row.id)}
                                    onDelete={() => {
                                        if (confirm('Delete this invoice?')) {
                                            router.delete(route('invoices.destroy', row.id));
                                        }
                                    }}
                                />
                            </div>
                        ),
                    },
                ]}
            />
        </ModuleLayout>
    );
}
