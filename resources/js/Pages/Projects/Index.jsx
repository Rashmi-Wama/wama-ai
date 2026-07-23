import ModuleLayout from '@/Layouts/ModuleLayout';
import CrudIndex from '@/Components/CrudIndex';
import { formatDate, formatMoney, RowActions, StatusBadge } from '@/Components/FormHelpers';
import { router, usePage } from '@inertiajs/react';

export default function Index({ projects, filters }) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const canCreate = permissions.includes('projects.create');
    const canUpdate = permissions.includes('projects.update');
    const canDelete = permissions.includes('projects.delete');

    return (
        <ModuleLayout title="Projects">
            <CrudIndex
                title="Projects"
                description="Track client projects, deadlines, and payments."
                createHref={route('projects.create')}
                createLabel="Add project"
                canCreate={canCreate}
                searchValue={filters.search}
                searchRoute={route('projects.index')}
                meta={projects}
                rows={projects.data}
                columns={[
                    { key: 'project_name', label: 'Project' },
                    {
                        key: 'client',
                        label: 'Client',
                        render: (row) => row.client?.company_name ?? '—',
                    },
                    {
                        key: 'start_date',
                        label: 'Start',
                        render: (row) => formatDate(row.start_date),
                    },
                    {
                        key: 'deadline',
                        label: 'Deadline',
                        render: (row) => formatDate(row.deadline),
                    },
                    {
                        key: 'total_amount',
                        label: 'Total',
                        render: (row) => formatMoney(row.total_amount),
                    },
                    {
                        key: 'project_status',
                        label: 'Status',
                        render: (row) => <StatusBadge value={row.project_status} />,
                    },
                    {
                        key: 'actions',
                        label: 'Actions',
                        render: (row) => (
                            <RowActions
                                canUpdate={canUpdate}
                                canDelete={canDelete}
                                editHref={route('projects.edit', row.id)}
                                onDelete={() => {
                                    if (confirm('Delete this project?')) {
                                        router.delete(route('projects.destroy', row.id));
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
