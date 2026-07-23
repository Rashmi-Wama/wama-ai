import ModuleLayout from '@/Layouts/ModuleLayout';
import { FormField, FormSelect, FormShell } from '@/Components/FormHelpers';
import { useForm } from '@inertiajs/react';

export default function Form({ user, roles }) {
    const isEdit = Boolean(user?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        role: user?.role ?? 'HR User',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('users.update', user.id));
        } else {
            post(route('users.store'));
        }
    };

    return (
        <ModuleLayout title={isEdit ? 'Edit User' : 'Add User'}>
            <FormShell
                title={isEdit ? 'Edit user' : 'Add user'}
                subtitle="Assign Super Admin, HR Admin, or HR User access."
                onSubmit={submit}
                processing={processing}
                cancelHref={route('users.index')}
                submitLabel={isEdit ? 'Update user' : 'Create user'}
            >
                <FormField
                    id="name"
                    label="Name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
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
                    id="password"
                    label={isEdit ? 'New password (optional)' : 'Password'}
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required={!isEdit}
                />
                <FormField
                    id="password_confirmation"
                    label="Confirm password"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    required={!isEdit}
                />
                <FormSelect
                    id="role"
                    label="Role"
                    value={data.role}
                    onChange={(e) => setData('role', e.target.value)}
                    error={errors.role}
                    options={roles.map((role) => ({ value: role, label: role }))}
                />
            </FormShell>
        </ModuleLayout>
    );
}
