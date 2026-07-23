import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ModuleSidebar from '@/Components/ModuleSidebar';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ModuleLayout({ title, children, sidebarExtra = null }) {
    const { flash } = usePage().props;
    const [message, setMessage] = useState(null);

    useEffect(() => {
        if (flash?.success) {
            setMessage({ type: 'success', text: flash.success });
        } else if (flash?.error) {
            setMessage({ type: 'error', text: flash.error });
        }
    }, [flash]);

    useEffect(() => {
        if (!message) {
            return undefined;
        }

        const timer = window.setTimeout(() => setMessage(null), 3500);
        return () => window.clearTimeout(timer);
    }, [message]);

    return (
        <AuthenticatedLayout sidebar={<ModuleSidebar>{sidebarExtra}</ModuleSidebar>}>
            <Head title={title} />

            <div className="overflow-y-auto py-6 sm:py-8 chat-scroll">
                <div className="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {message && (
                        <div
                            className={`rounded-2xl border px-4 py-3 text-sm animate-fade-up ${
                                message.type === 'success'
                                    ? 'border-sage/30 bg-sage/10 text-ink'
                                    : 'border-red-200 bg-red-50 text-red-700'
                            }`}
                        >
                            {message.text}
                        </div>
                    )}
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
