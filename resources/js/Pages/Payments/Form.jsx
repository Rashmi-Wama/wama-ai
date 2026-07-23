import ModuleLayout from '@/Layouts/ModuleLayout';
import { FormField, FormSelect, FormShell } from '@/Components/FormHelpers';
import { useForm } from '@inertiajs/react';

export default function Form({ payment, invoices }) {
    const isEdit = Boolean(payment?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        invoice_id: payment?.invoice_id?.toString() ?? '',
        amount: payment?.amount ?? '',
        payment_date: payment?.payment_date?.slice?.(0, 10) ?? payment?.payment_date ?? '',
        payment_mode: payment?.payment_mode ?? 'bank_transfer',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('payments.update', payment.id));
        } else {
            post(route('payments.store'));
        }
    };

    return (
        <ModuleLayout title={isEdit ? 'Edit Payment' : 'Add Payment'}>
            <FormShell
                title={isEdit ? 'Edit payment' : 'Record payment'}
                subtitle="Payments automatically update invoice paid amounts."
                onSubmit={submit}
                processing={processing}
                cancelHref={route('payments.index')}
                submitLabel={isEdit ? 'Update payment' : 'Save payment'}
            >
                <FormSelect
                    id="invoice_id"
                    label="Invoice"
                    value={data.invoice_id}
                    onChange={(e) => setData('invoice_id', e.target.value)}
                    error={errors.invoice_id}
                    options={invoices.map((invoice) => ({
                        value: String(invoice.id),
                        label: `${invoice.invoice_number}${invoice.client ? ` — ${invoice.client.company_name}` : ''}`,
                    }))}
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
                    id="payment_date"
                    label="Payment date"
                    type="date"
                    value={data.payment_date}
                    onChange={(e) => setData('payment_date', e.target.value)}
                    error={errors.payment_date}
                    required
                />
                <FormSelect
                    id="payment_mode"
                    label="Payment mode"
                    value={data.payment_mode}
                    onChange={(e) => setData('payment_mode', e.target.value)}
                    error={errors.payment_mode}
                    options={[
                        { value: 'cash', label: 'Cash' },
                        { value: 'bank_transfer', label: 'Bank transfer' },
                        { value: 'upi', label: 'UPI' },
                        { value: 'cheque', label: 'Cheque' },
                        { value: 'card', label: 'Card' },
                        { value: 'other', label: 'Other' },
                    ]}
                />
            </FormShell>
        </ModuleLayout>
    );
}
