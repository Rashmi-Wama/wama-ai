import ModuleLayout from '@/Layouts/ModuleLayout';
import { formatMoney } from '@/Components/FormHelpers';
import {
    Chart as ChartJS,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';
import { Bar, Doughnut } from 'react-chartjs-2';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const leaf = '#D85A38';
const sage = '#E5C4B3';
const clay = '#A84830';
const bark = '#3C2A21';

export default function Index({ summary, revenueByMonth, invoiceStatus, topClients }) {
    const revenueData = {
        labels: revenueByMonth.map((m) => m.label),
        datasets: [
            {
                label: 'Billed',
                data: revenueByMonth.map((m) => m.billed),
                backgroundColor: leaf,
                borderRadius: 8,
            },
            {
                label: 'Collected',
                data: revenueByMonth.map((m) => m.collected),
                backgroundColor: sage,
                borderRadius: 8,
            },
        ],
    };

    const statusData = {
        labels: invoiceStatus.map((s) => s.status.replaceAll('_', ' ')),
        datasets: [
            {
                data: invoiceStatus.map((s) => s.count),
                backgroundColor: [leaf, sage, clay, bark, '#c45c26'],
                borderWidth: 0,
            },
        ],
    };

    const topClientData = {
        labels: topClients.map((c) => c.client),
        datasets: [
            {
                label: 'Outstanding',
                data: topClients.map((c) => c.outstanding),
                backgroundColor: clay,
                borderRadius: 8,
            },
        ],
    };

    const cards = [
        { label: 'Outstanding', value: formatMoney(summary.outstanding_total) },
        { label: 'Collected this month', value: formatMoney(summary.collected_this_month) },
        { label: 'Active clients', value: summary.active_clients },
        { label: 'Delayed projects', value: summary.delayed_projects },
    ];

    return (
        <ModuleLayout title="Analytics">
            <div className="space-y-6 animate-fade-up">
                <div>
                    <h1 className="font-display text-3xl font-semibold text-bark">Analytics</h1>
                    <p className="mt-1 text-sm text-clay">
                        Live revenue, collections, and client outstanding snapshot.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <div
                            key={card.label}
                            className="rounded-3xl border border-sage/20 bg-white/50 px-5 py-4 shadow-soft"
                        >
                            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-clay">
                                {card.label}
                            </p>
                            <p className="mt-2 font-display text-2xl font-semibold text-bark">
                                {card.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-5 lg:grid-cols-2">
                    <section className="rounded-3xl border border-sage/20 bg-white/50 p-5 shadow-soft">
                        <h2 className="mb-4 text-sm font-semibold text-bark">Revenue (6 months)</h2>
                        <Bar
                            data={revenueData}
                            options={{
                                responsive: true,
                                plugins: { legend: { position: 'bottom' } },
                                scales: { y: { beginAtZero: true } },
                            }}
                        />
                    </section>

                    <section className="rounded-3xl border border-sage/20 bg-white/50 p-5 shadow-soft">
                        <h2 className="mb-4 text-sm font-semibold text-bark">Invoice status</h2>
                        <div className="mx-auto max-w-xs">
                            <Doughnut
                                data={statusData}
                                options={{
                                    plugins: { legend: { position: 'bottom' } },
                                }}
                            />
                        </div>
                    </section>

                    <section className="rounded-3xl border border-sage/20 bg-white/50 p-5 shadow-soft lg:col-span-2">
                        <h2 className="mb-4 text-sm font-semibold text-bark">Highest outstanding clients</h2>
                        <Bar
                            data={topClientData}
                            options={{
                                indexAxis: 'y',
                                responsive: true,
                                plugins: { legend: { display: false } },
                                scales: { x: { beginAtZero: true } },
                            }}
                        />
                    </section>
                </div>
            </div>
        </ModuleLayout>
    );
}
