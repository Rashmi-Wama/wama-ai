import { Head, Link } from '@inertiajs/react';

const features = [
    {
        title: 'Ask your business',
        description: 'Get instant answers about revenue, outstanding invoices, delayed projects, and client performance using live business data.',
        icon: (
            <path d="M8 10h8M8 14h5m8-2a9 9 0 1 1-3.3-7A9 9 0 0 1 21 12Z" />
        ),
    },
    {
        title: 'Manage daily work',
        description: 'Create, find, and update clients, projects, invoices, payments, and users through a natural conversation.',
        icon: (
            <>
                <path d="M4 6h16M4 12h16M4 18h10" />
                <circle cx="18" cy="18" r="2" />
            </>
        ),
    },
    {
        title: 'Create invoice PDFs',
        description: 'Generate professional invoices, download them as PDFs, and prepare invoice emails without leaving the chat.',
        icon: (
            <>
                <path d="M7 3h7l4 4v14H7z" />
                <path d="M14 3v5h5M9.5 14h5M9.5 17h3" />
            </>
        ),
    },
    {
        title: 'Track money clearly',
        description: 'See pending and overdue payments, monthly collections, top clients, and sales across any date range.',
        icon: (
            <>
                <path d="M4 19V9m6 10V5m6 14v-7m4 7H2" />
                <path d="m4 7 6-4 6 6 4-3" />
            </>
        ),
    },
    {
        title: 'Share useful summaries',
        description: 'Turn payment and operations data into concise WhatsApp-ready updates for your team and clients.',
        icon: (
            <>
                <path d="M20 11.5a8 8 0 0 1-11.9 7L3 20l1.5-5.1A8 8 0 1 1 20 11.5Z" />
                <path d="M8 11h.01M12 11h.01M16 11h.01" />
            </>
        ),
    },
    {
        title: 'Work with confidence',
        description: 'Role-based access and guarded actions keep sensitive data protected while your team moves faster.',
        icon: (
            <>
                <path d="M12 3 5 6v5c0 4.6 2.9 8 7 10 4.1-2 7-5.4 7-10V6z" />
                <path d="m9 12 2 2 4-4" />
            </>
        ),
    },
];

const examples = [
    'Which clients have overdue payments?',
    'Download the latest Apex Retail invoice as PDF.',
    'How is the business performing this month?',
];

function BrandMark() {
    return (
        <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-leaf text-white shadow-glow">
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2v4M12 18v4M2 12h4M18 12h4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M19.1 4.9l-2.8 2.8M7.7 16.3l-2.8 2.8" strokeLinecap="round" />
            </svg>
        </div>
    );
}

export default function Welcome({ auth }) {
    const destination = auth?.user ? route('dashboard') : route('login');

    return (
        <>
            <Head title="AI-powered business operations" />

            <div className="min-h-screen overflow-hidden bg-cream text-ink">
                <div className="orb -left-32 top-20 h-96 w-96" />
                <div className="orb -right-24 top-[34rem] h-80 w-80 opacity-60" />

                <header className="relative z-20 mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-10">
                    <Link href="/" className="flex items-center gap-3">
                        <BrandMark />
                        <div>
                            <span className="block font-display text-lg font-bold tracking-tight">Wama AI</span>
                            <span className="block text-[10px] font-semibold uppercase tracking-[0.24em] text-leaf/70">
                                Business intelligence
                            </span>
                        </div>
                    </Link>

                    <Link
                        href={destination}
                        className="rounded-full border border-leaf/20 bg-white/70 px-5 py-2.5 text-sm font-bold text-leaf shadow-sm backdrop-blur transition hover:-translate-y-0.5 hover:border-leaf/40 hover:bg-white"
                    >
                        {auth?.user ? 'Open dashboard' : 'Log in'}
                    </Link>
                </header>

                <main className="relative">
                    <section className="mx-auto grid min-h-[760px] max-w-7xl items-center gap-14 px-6 pb-24 pt-16 lg:grid-cols-[1.08fr_.92fr] lg:px-10 lg:pt-12">
                        <div className="relative z-10 animate-fade-up">
                            <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-sage/35 bg-white/60 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-leaf backdrop-blur">
                                <span className="h-2 w-2 rounded-full bg-sage shadow-[0_0_0_5px_rgba(144,169,85,.15)]" />
                                Your business, one conversation away
                            </div>

                            <h1 className="max-w-3xl font-display text-5xl font-bold leading-[1.06] tracking-[-0.04em] text-bark sm:text-6xl lg:text-7xl">
                                Run your business with
                                <span className="relative ml-3 inline-block text-leaf">
                                    clarity.
                                    <svg className="absolute -bottom-2 left-0 h-3 w-full text-sage/60" viewBox="0 0 240 12" preserveAspectRatio="none">
                                        <path d="M2 9C65 2 170 2 238 7" fill="none" stroke="currentColor" strokeWidth="5" strokeLinecap="round" />
                                    </svg>
                                </span>
                            </h1>

                            <p className="mt-8 max-w-2xl text-lg leading-8 text-bark/65 sm:text-xl">
                                Wama AI turns your business data into answers and actions. Ask questions, manage operations,
                                create invoices, track payments, and get decisions moving—all from one intelligent workspace.
                            </p>

                            <div className="mt-10 flex flex-wrap items-center gap-4">
                                <Link
                                    href={destination}
                                    className="group inline-flex items-center gap-3 rounded-full bg-leaf px-7 py-4 text-sm font-bold text-white shadow-glow transition hover:-translate-y-1 hover:bg-clay"
                                >
                                    {auth?.user ? 'Go to dashboard' : 'Log in to Wama AI'}
                                    <svg className="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <path d="M5 12h14m-6-6 6 6-6 6" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </Link>
                                <a href="#capabilities" className="px-4 py-4 text-sm font-bold text-bark/65 transition hover:text-leaf">
                                    Explore capabilities ↓
                                </a>
                            </div>

                            <div className="mt-12 flex flex-wrap gap-x-8 gap-y-3 text-sm font-semibold text-bark/55">
                                {['Live business data', 'Role-based access', 'Groq-powered AI'].map((item) => (
                                    <span key={item} className="flex items-center gap-2">
                                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-sage/20 text-leaf">✓</span>
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-xl animate-fade-up [animation-delay:180ms]">
                            <div className="absolute -inset-6 rounded-[3rem] bg-gradient-to-br from-sage/30 via-transparent to-leaf/15 blur-2xl" />
                            <div className="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/75 p-4 shadow-soft backdrop-blur-xl sm:p-6">
                                <div className="flex items-center justify-between border-b border-sage/15 pb-5">
                                    <div className="flex items-center gap-3">
                                        <BrandMark />
                                        <div>
                                            <p className="text-sm font-bold text-bark">Wama AI Assistant</p>
                                            <p className="flex items-center gap-1.5 text-xs text-leaf/65">
                                                <span className="h-1.5 w-1.5 rounded-full bg-sage" /> Ready to help
                                            </p>
                                        </div>
                                    </div>
                                    <span className="rounded-full bg-sage/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-leaf">
                                        Live data
                                    </span>
                                </div>

                                <div className="space-y-4 py-6">
                                    <div className="ml-auto max-w-[84%] rounded-3xl rounded-br-md bg-leaf px-5 py-4 text-sm leading-6 text-white shadow-md">
                                        How is our business performing this month?
                                    </div>
                                    <div className="max-w-[92%] rounded-3xl rounded-bl-md border border-sage/15 bg-cream/70 px-5 py-4 text-sm leading-6 text-bark">
                                        <p className="font-bold">Here’s your monthly overview:</p>
                                        <div className="mt-4 grid grid-cols-2 gap-3">
                                            <div className="rounded-2xl bg-white/80 p-3">
                                                <p className="text-[10px] font-bold uppercase tracking-wider text-bark/45">Revenue</p>
                                                <p className="mt-1 font-display text-lg font-bold text-leaf">₹8.75L</p>
                                            </div>
                                            <div className="rounded-2xl bg-white/80 p-3">
                                                <p className="text-[10px] font-bold uppercase tracking-wider text-bark/45">Collected</p>
                                                <p className="mt-1 font-display text-lg font-bold text-leaf">₹5.20L</p>
                                            </div>
                                        </div>
                                        <p className="mt-4 text-bark/65">I can also show overdue invoices or prepare a client summary.</p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3 rounded-2xl border border-sage/20 bg-white/80 px-4 py-3 text-sm text-bark/40">
                                    Ask anything about your business...
                                    <span className="ml-auto flex h-9 w-9 items-center justify-center rounded-full bg-leaf text-white">
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <path d="m5 12 14-7-4 14-3-6z" strokeLinejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="capabilities" className="relative bg-bark px-6 py-24 text-cream lg:px-10">
                        <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(#f4f3ee 1px, transparent 1px)', backgroundSize: '24px 24px' }} />
                        <div className="relative mx-auto max-w-7xl">
                            <div className="max-w-2xl">
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-sage">Built for everyday operations</p>
                                <h2 className="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">
                                    Less searching. More doing.
                                </h2>
                                <p className="mt-5 text-lg leading-8 text-cream/60">
                                    One secure assistant connects the details across your clients, projects, invoices, payments, and team.
                                </p>
                            </div>

                            <div className="mt-14 grid gap-px overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 md:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <article key={feature.title} className="group bg-bark p-8 transition hover:bg-clay/60 sm:p-10">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-sage/15 text-sage transition group-hover:scale-110 group-hover:bg-sage group-hover:text-bark">
                                            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
                                                {feature.icon}
                                            </svg>
                                        </div>
                                        <h3 className="mt-7 font-display text-xl font-bold">{feature.title}</h3>
                                        <p className="mt-3 text-sm leading-7 text-cream/55">{feature.description}</p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-6 py-24 lg:px-10">
                        <div className="mx-auto grid max-w-7xl items-center gap-16 lg:grid-cols-2">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-leaf">Simple by design</p>
                                <h2 className="mt-5 font-display text-4xl font-bold tracking-tight text-bark sm:text-5xl">
                                    Ask in plain language.<br />Get work done.
                                </h2>
                                <p className="mt-6 max-w-xl text-lg leading-8 text-bark/60">
                                    No complicated reports or buried menus. Tell Wama AI what you need and receive a clear answer,
                                    a saved update, or a ready-to-download document.
                                </p>

                                <div className="mt-10 space-y-5">
                                    {['Ask a business question', 'Wama AI securely reads the right data', 'Receive an answer or completed action'].map((step, index) => (
                                        <div key={step} className="flex items-center gap-4">
                                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-sage/35 bg-white font-display text-sm font-bold text-leaf shadow-sm">
                                                {index + 1}
                                            </span>
                                            <p className="font-semibold text-bark/75">{step}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-4">
                                {examples.map((example, index) => (
                                    <div
                                        key={example}
                                        className="flex items-center gap-4 rounded-2xl border border-sage/20 bg-white/65 p-5 shadow-sm backdrop-blur transition hover:-translate-y-1 hover:border-sage/45 hover:shadow-soft"
                                    >
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sage/15 text-leaf">
                                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <path d="M8 10h8M8 14h4M21 12a9 9 0 1 1-3.3-7" strokeLinecap="round" />
                                            </svg>
                                        </span>
                                        <p className="text-sm font-semibold text-bark/75">“{example}”</p>
                                        <span className="ml-auto text-xs font-bold text-sage">0{index + 1}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-6 pb-10 pt-6 lg:px-10">
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[2.5rem] bg-leaf px-8 py-16 text-center text-white shadow-glow sm:px-16">
                            <div className="absolute -left-16 -top-28 h-64 w-64 rounded-full border-[45px] border-white/5" />
                            <div className="absolute -bottom-32 -right-12 h-72 w-72 rounded-full border-[55px] border-white/5" />
                            <div className="relative">
                                <p className="text-xs font-bold uppercase tracking-[0.24em] text-cream/70">Your intelligent workspace</p>
                                <h2 className="mx-auto mt-5 max-w-3xl font-display text-4xl font-bold tracking-tight sm:text-5xl">
                                    Make every business decision clearer.
                                </h2>
                                <p className="mx-auto mt-5 max-w-2xl text-base leading-7 text-cream/75">
                                    Sign in to view live insights, manage operations, and turn conversations into completed work.
                                </p>
                                <Link
                                    href={destination}
                                    className="mt-9 inline-flex items-center gap-3 rounded-full bg-cream px-7 py-4 text-sm font-bold text-leaf shadow-lg transition hover:-translate-y-1 hover:bg-white"
                                >
                                    {auth?.user ? 'Open Wama AI' : 'Log in to continue'}
                                    <span aria-hidden="true">→</span>
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-6 py-10 text-sm text-bark/50 sm:flex-row lg:px-10">
                    <div className="flex items-center gap-3">
                        <BrandMark />
                        <span className="font-display font-bold text-bark">Wama AI</span>
                    </div>
                    <p>Business operations, made conversational.</p>
                    <Link href={destination} className="font-bold text-leaf hover:text-clay">
                        {auth?.user ? 'Dashboard' : 'Log in'}
                    </Link>
                </footer>
            </div>
        </>
    );
}
