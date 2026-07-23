import ModuleLayout from '@/Layouts/ModuleLayout';
import { FormField, FormSelect, FormShell } from '@/Components/FormHelpers';
import { useForm } from '@inertiajs/react';

export default function Form({ client }) {
    const isEdit = Boolean(client?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        company_name: client?.company_name ?? '',
        contact_person: client?.contact_person ?? '',
        email: client?.email ?? '',
        mobile: client?.mobile ?? '',
        status: client?.status ?? 'active',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('clients.update', client.id));
        } else {
            post(route('clients.store'));
        }
    };

    return (
        <ModuleLayout title={isEdit ? 'Edit Client' : 'Add Client'}>
            <FormShell
                title={isEdit ? 'Edit client' : 'Add client'}
                subtitle="Company details used across projects and invoices."
                onSubmit={submit}
                processing={processing}
                cancelHref={route('clients.index')}
                submitLabel={isEdit ? 'Update client' : 'Create client'}
            >
                <FormField
                    id="company_name"
                    label="Company name"
                    value={data.company_name}
                    onChange={(e) => setData('company_name', e.target.value)}
                    error={errors.company_name}
                    required
                />
                <FormField
                    id="contact_person"
                    label="Contact person"
                    value={data.contact_person}
                    onChange={(e) => setData('contact_person', e.target.value)}
                    error={errors.contact_person}
                    required
                />
                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                />
                <FormField
                    id="mobile"
                    label="Mobile"
                    value={data.mobile}
                    onChange={(e) => setData('mobile', e.target.value)}
                    error={errors.mobile}
                    required
                />
                <FormSelect
                    id="status"
                    label="Status"
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                    error={errors.status}
                    options={[
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' },
                    ]}
                />
            </FormShell>
        </ModuleLayout>
    );
}
