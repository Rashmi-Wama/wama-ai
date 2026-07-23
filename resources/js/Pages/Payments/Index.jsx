import ModuleLayout from '@/Layouts/ModuleLayout';
import CrudIndex from '@/Components/CrudIndex';
import { formatDate, formatMoney, RowActions } from '@/Components/FormHelpers';
import { router, usePage } from '@inertiajs/react';

export default function Index({ payments, filters }) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const canCreate = permissions.includes('payments.create');
    const canUpdate = permissions.includes('payments.update');
    const canDelete = permissions.includes('payments.delete');

    return (
        <ModuleLayout title="Payments">
            <CrudIndex
                title="Payments"
                description="Record payments against invoices."
                createHref={route('payments.create')}
                createLabel="Add payment"
                canCreate={canCreate}
                searchValue={filters.search}
                searchRoute={route('payments.index')}
                meta={payments}
                rows={payments.data}
                columns={[
                    {
                        key: 'invoice',
                        label: 'Invoice',
                        render: (row) => row.invoice?.invoice_number ?? '—',
                    },
                    {
                        key: 'client',
                        label: 'Client',
                        render: (row) => row.invoice?.client?.company_name ?? '—',
                    },
                    {
                        key: 'amount',
                        label: 'Amount',
                        render: (row) => formatMoney(row.amount),
                    },
                    {
                        key: 'payment_date',
                        label: 'Date',
                        render: (row) => formatDate(row.payment_date),
                    },
                    {
                        key: 'payment_mode',
                        label: 'Mode',
                        render: (row) => String(row.payment_mode).replaceAll('_', ' '),
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        render: (row) => (
                            <RowActions
                                canUpdate={canUpdate}
                                canDelete={canDelete}
                                editHref={route('payments.edit', row.id)}
                                onDelete={() => {
                                    if (confirm('Delete this payment?')) {
                                        router.delete(route('payments.destroy', row.id));
                                    }
                                }}
                            />
                        ),
                    },
                ]}
            />
        </ModuleLayout>
    );
}
