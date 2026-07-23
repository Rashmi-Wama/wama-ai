import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

function Pagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2 pt-2">
            {links.map((link, index) => {
                const label = link.label
                    .replace('&laquo;', '«')
                    .replace('&raquo;', '»');

                if (!link.url) {
                    return (
                        <span
                            key={`${label}-${index}`}
                            className="rounded-xl px-3 py-1.5 text-sm text-clay/60"
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    );
                }

                return (
                    <Link
                        key={`${label}-${index}`}
                        href={link.url}
                        className={`rounded-xl px-3 py-1.5 text-sm transition ${
                            link.active
                                ? 'bg-leaf text-white'
                                : 'bg-cream text-ink hover:bg-sage/10'
                        }`}
                        dangerouslySetInnerHTML={{ __html: label }}
                        preserveScroll
                    />
                );
            })}
        </div>
    );
}

export default function CrudIndex({
    title,
    description,
    createHref,
    createLabel = 'Add new',
    canCreate = false,
    searchValue = '',
    searchRoute,
    columns,
    rows,
    emptyLabel = 'No records found.',
    meta,
}) {
    const [search, setSearch] = useState(searchValue ?? '');

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(
            searchRoute,
            { search },
            { preserveState: true, replace: true },
        );
    };

    return (
        <div className="space-y-5 animate-fade-up">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="font-display text-2xl font-semibold text-bark sm:text-3xl">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 text-sm text-clay">{description}</p>
                    )}
                </div>
                {canCreate && createHref && (
                    <Link href={createHref}>
                        <PrimaryButton type="button">{createLabel}</PrimaryButton>
                    </Link>
                )}
            </div>

            <form onSubmit={submitSearch} className="flex gap-3">
                <TextInput
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search…"
                    className="w-full max-w-sm"
                />
                <PrimaryButton type="submit">Search</PrimaryButton>
            </form>

            <div className="glass-panel overflow-hidden rounded-3xl">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-sage/20 bg-sage/5 text-[11px] uppercase tracking-[0.14em] text-clay">
                            <tr>
                                {columns.map((column) => (
                                    <th key={column.key} className="px-4 py-3 font-semibold">
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={columns.length}
                                        className="px-4 py-10 text-center text-clay"
                                    >
                                        {emptyLabel}
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-sage/10 last:border-0 hover:bg-sage/5"
                                    >
                                        {columns.map((column) => (
                                            <td key={column.key} className="px-4 py-3 text-ink">
                                                {column.render
                                                    ? column.render(row)
                                                    : row[column.key]}
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {meta?.links && <Pagination links={meta.links} />}
        </div>
    );
}
