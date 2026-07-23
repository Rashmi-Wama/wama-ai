import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({
    title,
    subtitle,
    children,
}) {
    return (
        <div className="relative flex min-h-screen overflow-hidden">
            <div className="orb -left-24 -top-20 h-72 w-72 animate-pulse-soft" />
            <div className="orb bottom-0 right-0 h-96 w-96 animate-float opacity-70" />

            <div className="relative z-10 hidden w-1/2 flex-col justify-between border-r border-sage/30 bg-gradient-to-br from-bark via-clay to-leaf p-10 lg:flex xl:p-14">
                <Link href="/" className="inline-flex items-center gap-3 animate-fade-up">
                    <ApplicationLogo className="h-11 w-11" />
                    <span className="font-display text-2xl font-semibold tracking-tight text-cream">
                        Wama AI
                    </span>
                </Link>

                <div className="max-w-md animate-fade-up" style={{ animationDelay: '120ms' }}>
                    <p className="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-clay">
                        Business intelligence
                    </p>
                    <h1 className="font-display text-4xl font-semibold leading-tight text-cream xl:text-5xl">
                        Your AI partner for clients, projects & decisions.
                    </h1>
                    <p className="mt-5 text-base leading-relaxed text-cream/80">
                        Ask questions, explore insights, and move work forward
                        with a calm, modern assistant built for growing teams.
                    </p>

                    <div className="mt-10 grid grid-cols-2 gap-4">
                        <div className="rounded-2xl border border-cream/15 bg-cream/10 p-4 backdrop-blur-sm animate-float">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-clay text-ink">
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                                    <path d="M12 3v18M3 12h18" strokeLinecap="round" />
                                    <circle cx="12" cy="12" r="8" />
                                </svg>
                            </div>
                            <p className="font-display text-sm font-semibold text-cream">Smart prompts</p>
                            <p className="mt-1 text-xs text-cream/70">Guided suggestions for everyday business work.</p>
                        </div>
                        <div className="rounded-2xl border border-cream/15 bg-cream/10 p-4 backdrop-blur-sm animate-float-delayed">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-cream/15 text-clay">
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                                    <path d="M4 19V5m0 14h16M8 15l3-4 3 2 4-6" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </div>
                            <p className="font-display text-sm font-semibold text-cream">Live clarity</p>
                            <p className="mt-1 text-xs text-cream/70">Clear answers styled for decision-making.</p>
                        </div>
                    </div>
                </div>

                <p className="text-sm text-cream/60">Secure access for your Wama workspace.</p>
            </div>

            <div className="relative z-10 flex w-full flex-col justify-center px-5 py-10 sm:px-8 lg:w-1/2">
                <div className="mx-auto mb-8 flex items-center gap-3 lg:hidden">
                    <ApplicationLogo className="h-10 w-10" />
                    <span className="font-display text-xl font-semibold text-bark">Wama AI</span>
                </div>

                <div className="mx-auto w-full max-w-md animate-fade-up">
                    {(title || subtitle) && (
                        <div className="mb-6">
                            {title && (
                                <h2 className="font-display text-3xl font-semibold text-bark">
                                    {title}
                                </h2>
                            )}
                            {subtitle && (
                                <p className="mt-2 text-sm leading-relaxed text-clay">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="auth-card">{children}</div>
                </div>
            </div>
        </div>
    );
}
