export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center justify-center rounded-2xl border border-leaf/25 bg-cream px-5 py-2.5 text-sm font-semibold text-ink transition duration-200 ease-out hover:-translate-y-0.5 hover:border-leaf/40 hover:bg-sage/30 focus:outline-none focus:ring-2 focus:ring-leaf focus:ring-offset-2 focus:ring-offset-cream disabled:pointer-events-none disabled:opacity-40 ` +
                className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
