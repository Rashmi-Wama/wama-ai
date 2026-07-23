import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout
            title="Reset access"
            subtitle="Enter your email and we’ll send a secure link to choose a new password."
        >
            <Head title="Forgot Password" />

            {status && (
                <div className="mb-4 rounded-2xl border border-sage/30 bg-sage/10 px-4 py-3 text-sm font-medium text-ink">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@company.com"
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <PrimaryButton className="w-full" disabled={processing}>
                    Email reset link
                </PrimaryButton>

                <p className="text-center text-sm text-clay">
                    Remembered it?{' '}
                    <Link href={route('login')} className="font-semibold text-bark underline decoration-sage underline-offset-4">
                        Back to sign in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
