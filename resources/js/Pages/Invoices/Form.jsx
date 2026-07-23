import ModuleLayout from '@/Layouts/ModuleLayout';
import { FormField, FormSelect, FormShell } from '@/Components/FormHelpers';
import { useForm } from '@inertiajs/react';

export default function Form({ invoice, clients }) {
    const isEdit = Boolean(invoice?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        client_id: invoice?.client_id?.toString() ?? '',
        invoice_number: invoice?.invoice_number ?? '',
        invoice_date: invoice?.invoice_date?.slice?.(0, 10) ?? invoice?.invoice_date ?? '',
        due_date: invoice?.due_date?.slice?.(0, 10) ?? invoice?.due_date ?? '',
        amount: invoice?.amount ?? '0',
        paid_amount: invoice?.paid_amount ?? '0',
        payment_status: invoice?.payment_status ?? 'unpaid',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('invoices.update', invoice.id));
        } else {
            post(route('invoices.store'));
        }
    };

    return (
        <ModuleLayout title={isEdit ? 'Edit Invoice' : 'Add Invoice'}>
            <FormShell
                title={isEdit ? 'Edit invoice' : 'Add invoice'}
                subtitle="Bill clients and track payment progress."
                onSubmit={submit}
                processing={processing}
                cancelHref={route('invoices.index')}
                submitLabel={isEdit ? 'Update invoice' : 'Create invoice'}
            >
                <FormSelect
                    id="client_id"
                    label="Client"
                    value={data.client_id}
                    onChange={(e) => setData('client_id', e.target.value)}
                    error={errors.client_id}
                    options={clients.map((c) => ({ value: String(c.id), label: c.company_name }))}
                />
                <FormField
                    id="invoice_number"
                    label="Invoice number"
                    value={data.invoice_number}
                    onChange={(e) => setData('invoice_number', e.target.value)}
                    error={errors.invoice_number}
                    required
                />
                <FormField
                    id="invoice_date"
                    label="Invoice date"
                    type="date"
                    value={data.invoice_date}
                    onChange={(e) => setData('invoice_date', e.target.value)}
                    error={errors.invoice_date}
                    required
                />
                <FormField
                    id="due_date"
                    label="Due date"
                    type="date"
                    value={data.due_date}
                    onChange={(e) => setData('due_date', e.target.value)}
                    error={errors.due_date}
                />
                <FormField
                    id="amount"
                    label="Amount"
                    type="number"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    error={errors.amount}
                    required
                />
                <FormField
                    id="paid_amount"
                    label="Paid amount"
                    type="number"
                    value={data.paid_amount}
                    onChange={(e) => setData('paid_amount', e.target.value)}
                    error={errors.paid_amount}
                    required
                />
                <FormSelect
                    id="payment_status"
                    label="Payment status"
                    value={data.payment_status}
                    onChange={(e) => setData('payment_status', e.target.value)}
                    error={errors.payment_status}
                    options={[
                        { value: 'unpaid', label: 'Unpaid' },
                        { value: 'partial', label: 'Partial' },
                        { value: 'paid', label: 'Paid' },
                        { value: 'overdue', label: 'Overdue' },
                    ]}
                />
            </FormShell>
        </ModuleLayout>
    );
}
