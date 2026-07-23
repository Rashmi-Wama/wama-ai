import ModuleLayout from '@/Layouts/ModuleLayout';
import CrudIndex from '@/Components/CrudIndex';
import { RowActions, StatusBadge } from '@/Components/FormHelpers';
import { router, usePage } from '@inertiajs/react';

export default function Index({ clients, filters }) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const canCreate = permissions.includes('clients.create');
    const canUpdate = permissions.includes('clients.update');
    const canDelete = permissions.includes('clients.delete');

    return (
        <ModuleLayout title="Clients">
            <CrudIndex
                title="Clients"
                description="Manage client companies and contacts."
                createHref={route('clients.create')}
                createLabel="Add client"
                canCreate={canCreate}
                searchValue={filters.search}
                searchRoute={route('clients.index')}
                meta={clients}
                rows={clients.data}
                columns={[
                    { key: 'company_name', label: 'Company' },
                    { key: 'contact_person', label: 'Contact' },
                    { key: 'email', label: 'Email' },
                    { key: 'mobile', label: 'Mobile' },
                    {
                        key: 'status',
                        label: 'Status',
                        render: (row) => <StatusBadge value={row.status} />,
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        render: (row) => (
                            <RowActions
                                canUpdate={canUpdate}
                                canDelete={canDelete}
                                editHref={route('clients.edit', row.id)}
                                onDelete={() => {
                                    if (confirm('Delete this client?')) {
                                        router.delete(route('clients.destroy', row.id));
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
