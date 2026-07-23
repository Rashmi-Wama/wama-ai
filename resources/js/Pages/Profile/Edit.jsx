import ModuleLayout from '@/Layouts/ModuleLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <ModuleLayout title="Profile">
            <div className="mx-auto max-w-3xl space-y-6 animate-fade-up">
                <div>
                    <h1 className="font-display text-2xl font-semibold text-bark">Profile</h1>
                    <p className="mt-1 text-sm text-clay">
                        Manage your account details and security.
                    </p>
                </div>

                <div className="glass-panel rounded-3xl p-6 sm:p-8">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </div>

                <div className="glass-panel rounded-3xl p-6 sm:p-8">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div className="glass-panel rounded-3xl p-6 sm:p-8">
                    <DeleteUserForm className="max-w-xl" />
                </div>
            </div>
        </ModuleLayout>
    );
}
