import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';

export function FormSelect({
    id,
    label,
    value,
    onChange,
    options,
    error,
    placeholder = 'Select…',
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <select
                id={id}
                value={value}
                onChange={onChange}
                className="mt-1.5 block w-full rounded-2xl border-sage/30 bg-cream/70 text-ink shadow-sm focus:border-leaf focus:ring-leaf"
            >
                <option value="">{placeholder}</option>
                {options.map((option) => {
                    const optionValue = typeof option === 'object' ? option.value : option;
                    const optionLabel = typeof option === 'object' ? option.label : option;

                    return (
                        <option key={optionValue} value={optionValue}>
                            {optionLabel}
                        </option>
                    );
                })}
            </select>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

export function FormField({
    id,
    label,
    type = 'text',
    value,
    onChange,
    error,
    placeholder,
    required = false,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                type={type}
                value={value}
                className="mt-1.5 block w-full"
                onChange={onChange}
                placeholder={placeholder}
                required={required}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

export function FormShell({
    title,
    subtitle,
    onSubmit,
    processing,
    cancelHref,
    children,
    submitLabel = 'Save',
}) {
    return (
        <div className="mx-auto max-w-3xl space-y-5 animate-fade-up">
            <div>
                <h1 className="font-display text-2xl font-semibold text-bark sm:text-3xl">
                    {title}
                </h1>
                {subtitle && <p className="mt-1 text-sm text-clay">{subtitle}</p>}
            </div>

            <form onSubmit={onSubmit} className="glass-panel space-y-5 rounded-3xl p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">{children}</div>

                <div className="flex flex-wrap gap-3 border-t border-sage/20 pt-5">
                    <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                    <Link href={cancelHref}>
                        <SecondaryButton type="button">Cancel</SecondaryButton>
                    </Link>
                </div>
            </form>
        </div>
    );
}

export function StatusBadge({ value }) {
    const tone = {
        active: 'bg-sage/15 text-sage',
        inactive: 'bg-clay/20 text-bark',
        pending: 'bg-clay/20 text-bark',
        in_progress: 'bg-sage/15 text-sage',
        completed: 'bg-leaf/15 text-leaf',
        on_hold: 'bg-clay/20 text-bark',
        cancelled: 'bg-red-50 text-red-700',
        unpaid: 'bg-red-50 text-red-700',
        partial: 'bg-clay/20 text-bark',
        paid: 'bg-sage/15 text-sage',
        overdue: 'bg-red-50 text-red-700',
    }[value] ?? 'bg-sage/10 text-ink';

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${tone}`}>
            {String(value).replaceAll('_', ' ')}
        </span>
    );
}

export function RowActions({ editHref, onDelete, canUpdate, canDelete }) {
    return (
        <div className="flex items-center gap-2">
            {canUpdate && editHref && (
                <Link
                    href={editHref}
                    className="rounded-xl bg-sage/10 px-3 py-1.5 text-xs font-semibold text-sage transition hover:bg-sage hover:text-white"
                >
                    Edit
                </Link>
            )}
            {canDelete && (
                <button
                    type="button"
                    onClick={onDelete}
                    className="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-600 hover:text-white"
                >
                    Delete
                </button>
            )}
        </div>
    );
}

export function formatMoney(value) {
    const amount = Number(value ?? 0);
    return amount.toLocaleString(undefined, {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    });
}

export function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
