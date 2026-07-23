import ModuleLayout from '@/Layouts/ModuleLayout';
import CrudIndex from '@/Components/CrudIndex';
import { RowActions, StatusBadge } from '@/Components/FormHelpers';
import { router, usePage } from '@inertiajs/react';

export default function Index({ users, filters }) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const canCreate = permissions.includes('users.create');
    const canUpdate = permissions.includes('users.update');
    const canDelete = permissions.includes('users.delete');

    return (
        <ModuleLayout title="Users">
            <CrudIndex
                title="Users"
                description="Manage user accounts and assign roles."
                createHref={route('users.create')}
                createLabel="Add user"
                canCreate={canCreate}
                searchValue={filters.search}
                searchRoute={route('users.index')}
                meta={users}
                rows={users.data}
                columns={[
                    { key: 'name', label: 'Name' },
                    { key: 'email', label: 'Email' },
                    {
                        key: 'role',
                        label: 'Role',
                        render: (row) => (
                            <StatusBadge value={row.roles?.[0]?.name ?? 'none'} />
                        ),
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        render: (row) => (
                            <RowActions
                                canUpdate={canUpdate}
                                canDelete={canDelete}
                                editHref={route('users.edit', row.id)}
                                onDelete={() => {
                                    if (confirm('Delete this user?')) {
                                        router.delete(route('users.destroy', row.id));
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
