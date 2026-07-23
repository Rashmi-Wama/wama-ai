export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-2xl border border-transparent bg-leaf px-5 py-2.5 text-sm font-semibold text-white shadow-glow transition duration-200 ease-out hover:-translate-y-0.5 hover:bg-clay focus:outline-none focus:ring-2 focus:ring-leaf focus:ring-offset-2 focus:ring-offset-cream active:translate-y-0 disabled:pointer-events-none disabled:opacity-40 ` +
                className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
