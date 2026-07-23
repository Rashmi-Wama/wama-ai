import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="Verify your email"
            subtitle="Thanks for signing up. Please verify your email address to unlock your Wama AI workspace."
        >
            <Head title="Email Verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-2xl border border-sage/30 bg-sage/10 px-4 py-3 text-sm font-medium text-ink">
                    A new verification link has been sent to your email address.
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <PrimaryButton className="w-full" disabled={processing}>
                    Resend verification email
                </PrimaryButton>

                <p className="text-center text-sm text-clay">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="font-semibold text-bark underline decoration-sage underline-offset-4"
                    >
                        Log out
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
