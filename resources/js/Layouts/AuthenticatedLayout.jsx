import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({
    sidebar,
    children,
    contentClassName = '',
}) {
    const user = usePage().props.auth.user;
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

    return (
        <div className="relative flex h-screen overflow-hidden bg-cream">
            <div className="orb -left-16 top-10 h-64 w-64 opacity-50" />
            <div className="orb bottom-10 right-10 h-72 w-72 opacity-40" />

            {mobileSidebarOpen && (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    className="fixed inset-0 z-40 bg-ink/40 backdrop-blur-sm lg:hidden"
                    onClick={() => setMobileSidebarOpen(false)}
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-[280px] flex-col border-r border-sage/20 bg-cream/95 backdrop-blur-xl transition-transform duration-300 lg:static lg:translate-x-0 ${
                    mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center justify-between border-b border-sage/20 px-4 py-4">
                    <Link href={route('home')} className="flex items-center gap-2.5">
                        <ApplicationLogo className="h-9 w-9" />
                        <div>
                            <p className="font-display text-sm font-semibold text-bark">Wama AI</p>
                            <p className="text-[11px] text-clay">Business assistant</p>
                        </div>
                    </Link>
                    <button
                        type="button"
                        className="rounded-xl p-2 text-clay hover:bg-sage/10 hover:text-bark lg:hidden"
                        onClick={() => setMobileSidebarOpen(false)}
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M6 6l12 12M18 6L6 18" strokeLinecap="round" />
                        </svg>
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto px-3 py-4 chat-scroll">
                    {sidebar}
                </div>

                <div className="border-t border-sage/20 p-3">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="flex w-full items-center gap-3 rounded-2xl border border-sage/20 bg-sage/10 px-3 py-2.5 text-left transition hover:bg-sage/15"
                            >
                                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-leaf font-display text-sm font-semibold text-white">
                                    {user.name?.charAt(0)?.toUpperCase()}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-bark">{user.name}</p>
                                    <p className="truncate text-xs text-clay">{user.email}</p>
                                </div>
                                <svg className="h-4 w-4 text-clay" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fillRule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="left" width="48" contentClasses="py-1 bg-cream">
                            <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                Log Out
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </aside>

            <div className="relative z-10 flex min-w-0 flex-1 flex-col">
                <header className="flex items-center gap-3 border-b border-sage/20 px-4 py-3 lg:hidden">
                    <button
                        type="button"
                        className="rounded-xl border border-sage/20 bg-cream p-2 text-ink"
                        onClick={() => setMobileSidebarOpen(true)}
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M4 7h16M4 12h16M4 17h16" strokeLinecap="round" />
                        </svg>
                    </button>
                    <div>
                        <p className="font-display text-sm font-semibold text-bark">Wama AI</p>
                        <p className="text-xs text-clay">Chat assistant</p>
                    </div>
                </header>

                <main className={`relative flex min-h-0 flex-1 flex-col ${contentClassName}`}>
                    {children}
                </main>
            </div>
        </div>
    );
}
