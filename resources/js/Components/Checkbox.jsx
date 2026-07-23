export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-sage text-leaf shadow-sm focus:ring-leaf ' +
                className
            }
        />
    );
}
