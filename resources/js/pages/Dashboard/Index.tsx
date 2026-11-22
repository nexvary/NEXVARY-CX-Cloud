import { useTranslation } from 'react-i18next';
import { Cable, Headphones, PhoneCall, Server, Ticket } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
    stats: { extensions: number; trunks: number; calls_today: number; open_tickets: number };
    pbx_health: { connected: boolean; version?: string };
    recent_calls: { id: string; caller_id_number: string; destination_number: string; duration_seconds: number; started_at: string }[];
}

export default function Dashboard({ stats, pbx_health, recent_calls }: Props) {
    const { t, i18n } = useTranslation();
    const cards = [
        { label: 'nav.extensions', count: stats.extensions, Icon: Headphones, color: 'text-sky-300' },
        { label: 'nav.trunks', count: stats.trunks, Icon: Cable, color: 'text-violet-300' },
        { label: 'dashboard.today_calls', count: stats.calls_today, Icon: PhoneCall, color: 'text-emerald-300' },
        { label: 'dashboard.open_tickets', count: stats.open_tickets, Icon: Ticket, color: 'text-amber-300' },
    ];
    const format = new Intl.NumberFormat(i18n.language);

    return <AppLayout title={t('dashboard.title')}>
        <h1 className="text-2xl font-semibold">{t('dashboard.title')}</h1>
        <p className="mb-5 mt-1 text-sm nx-quiet">{t('dashboard.subtitle')}</p>
        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
            {cards.map(({ label, count, Icon, color }) => <div className="nx-card p-3 sm:p-4" key={label}>
                <Icon aria-hidden="true" className={`mb-3 size-5 ${color}`} />
                <p className="text-sm nx-quiet">{t(label)}</p>
                <p className="mt-1 text-2xl font-semibold">{format.format(count)}</p>
            </div>)}
        </div>
        <div className="nx-card my-4 flex flex-wrap items-center gap-3 p-4" role="status">
            <Server aria-hidden="true" className="size-5 text-sky-300" />
            <span className="font-medium">FreeSWITCH</span>
            <span className={pbx_health.connected ? 'text-emerald-300' : 'text-amber-300'}>
                {t(pbx_health.connected ? 'status.connected' : 'status.disconnected')}
            </span>
        </div>
        <section className="nx-card p-4">
            <h2 className="mb-3 text-base font-semibold">{t('dashboard.recent_calls')}</h2>
            {recent_calls.length === 0 ? <p className="text-sm nx-quiet">{t('dashboard.no_recent_calls')}</p> :
                <ul className="divide-y divide-[#3b414e]">
                    {recent_calls.map(call => <li key={call.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <span><bdi>{call.caller_id_number}</bdi> · <bdi>{call.destination_number}</bdi></span>
                        <span className="nx-quiet">{format.format(call.duration_seconds)} {t('management.seconds')}</span>
                        <time className="nx-quiet" dateTime={call.started_at}>{new Date(call.started_at).toLocaleString(i18n.language)}</time>
                    </li>)}
                </ul>}
        </section>
    </AppLayout>;
}
