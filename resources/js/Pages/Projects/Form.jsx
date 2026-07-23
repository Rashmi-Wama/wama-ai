import ModuleLayout from '@/Layouts/ModuleLayout';
import { FormField, FormSelect, FormShell } from '@/Components/FormHelpers';
import { useForm } from '@inertiajs/react';

export default function Form({ project, clients }) {
    const isEdit = Boolean(project?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        client_id: project?.client_id?.toString() ?? '',
        project_name: project?.project_name ?? '',
        start_date: project?.start_date?.slice?.(0, 10) ?? project?.start_date ?? '',
        deadline: project?.deadline?.slice?.(0, 10) ?? project?.deadline ?? '',
        total_amount: project?.total_amount ?? '0',
        payment_received: project?.payment_received ?? '0',
        project_status: project?.project_status ?? 'pending',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('projects.update', project.id));
        } else {
            post(route('projects.store'));
        }
    };

    return (
        <ModuleLayout title={isEdit ? 'Edit Project' : 'Add Project'}>
            <FormShell
                title={isEdit ? 'Edit project' : 'Add project'}
                subtitle="Link work to a client with budget and timeline."
                onSubmit={submit}
                processing={processing}
                cancelHref={route('projects.index')}
                submitLabel={isEdit ? 'Update project' : 'Create project'}
            >
                <FormSelect
                    id="client_id"
                    label="Client"
                    value={data.client_id}
                    onChange={(e) => setData('client_id', e.target.value)}
                    error={errors.client_id}
                    options={clients.map((c) => ({ value: String(c.id), label: c.company_name }))}
                />
                <FormField
                    id="project_name"
                    label="Project name"
                    value={data.project_name}
                    onChange={(e) => setData('project_name', e.target.value)}
                    error={errors.project_name}
                    required
                />
                <FormField
                    id="start_date"
                    label="Start date"
                    type="date"
                    value={data.start_date}
                    onChange={(e) => setData('start_date', e.target.value)}
                    error={errors.start_date}
                    required
                />
                <FormField
                    id="deadline"
                    label="Deadline"
                    type="date"
                    value={data.deadline}
                    onChange={(e) => setData('deadline', e.target.value)}
                    error={errors.deadline}
                />
                <FormField
                    id="total_amount"
                    label="Total amount"
                    type="number"
                    value={data.total_amount}
                    onChange={(e) => setData('total_amount', e.target.value)}
                    error={errors.total_amount}
                    required
                />
                <FormField
                    id="payment_received"
                    label="Payment received"
                    type="number"
                    value={data.payment_received}
                    onChange={(e) => setData('payment_received', e.target.value)}
                    error={errors.payment_received}
                    required
                />
                <FormSelect
                    id="project_status"
                    label="Status"
                    value={data.project_status}
                    onChange={(e) => setData('project_status', e.target.value)}
                    error={errors.project_status}
                    options={[
                        { value: 'pending', label: 'Pending' },
                        { value: 'in_progress', label: 'In progress' },
                        { value: 'completed', label: 'Completed' },
                        { value: 'on_hold', label: 'On hold' },
                        { value: 'cancelled', label: 'Cancelled' },
                    ]}
                />
            </FormShell>
        </ModuleLayout>
    );
}
