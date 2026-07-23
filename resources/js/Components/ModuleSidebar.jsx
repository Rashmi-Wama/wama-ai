import { Link, usePage } from '@inertiajs/react';

function can(permissions, name) {
    return permissions?.includes(name);
}

const NAV_ITEMS = [
    {
        label: 'AI Chat',
        route: 'dashboard',
        permission: 'ai-chatbot.view',
        match: 'dashboard',
    },
    {
        label: 'Clients',
        route: 'clients.index',
        permission: 'clients.view',
        match: 'clients.*',
    },
    {
        label: 'Projects',
        route: 'projects.index',
        permission: 'projects.view',
        match: 'projects.*',
    },
    {
        label: 'Invoices',
        route: 'invoices.index',
        permission: 'invoices.view',
        match: 'invoices.*',
    },
    {
        label: 'Payments',
        route: 'payments.index',
        permission: 'payments.view',
        match: 'payments.*',
    },
    {
        label: 'Analytics',
        route: 'analytics.index',
        permission: 'clients.view',
        match: 'analytics.*',
    },
    {
        label: 'Users',
        route: 'users.index',
        permission: 'users.view',
        match: 'users.*',
    },
];

export default function ModuleSidebar({ children }) {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];

    const items = NAV_ITEMS.filter((item) => {
        if (!item.permission) {
            return true;
        }

        if (item.permission === 'ai-chatbot.view') {
            return can(permissions, 'ai-chatbot.view')
                || can(permissions, 'clients.view')
                || can(permissions, 'users.view');
        }

        return can(permissions, item.permission);
    });

    return (
        <div className="flex h-full flex-col gap-4">
            <nav className="space-y-1.5">
                <p className="mb-2 px-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-clay">
                    Modules
                </p>
                {items.map((item) => {
                    const active = route().current(item.match);
                    const href = route(item.route);

                    return (
                        <Link
                            key={item.route}
                            href={href}
                            className={`block rounded-2xl px-3 py-2.5 text-sm transition ${
                                active
                                    ? 'bg-sage text-white shadow-sm'
                                    : 'text-clay hover:bg-sage/10 hover:text-bark'
                            }`}
                        >
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            {children && <div className="mt-2 border-t border-sage/20 pt-4">{children}</div>}
        </div>
    );
}
