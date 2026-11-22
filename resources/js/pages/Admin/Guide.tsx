import { Link, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, ArrowRight, AudioLines, Cable, ContactRound, CreditCard, Headphones, ListOrdered, MessageSquare, Route, Server, Shield, Workflow } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const icons = {
    trunks: Cable, extensions: Headphones, inbound: Route, outbound: Route, ivr: Workflow, queues: ListOrdered,
    sms: MessageSquare, contacts: ContactRound, billing: CreditCard, voice: AudioLines, security: Shield, maintenance: Server,
};
type Module = keyof typeof icons;
type Row = Record<string, string | number | boolean | null>;

export default function Guide({ modules, module, columns = [], records, checks = {}, draftFields = [] }: {
    modules: Module[]; module?: Module; columns?: string[]; draftFields?: string[];
    records?: { data: Row[]; next_page_url?: string; prev_page_url?: string }; checks?: Record<string, boolean>;
}) {
    const { t, i18n } = useTranslation();
    const { flash } = usePage<{ flash?: { success?: string } }>().props;
    const form = useForm({ first_name: '', last_name: '', email: '', phone: '' });
    const Back = i18n.dir() === 'rtl' ? ArrowRight : ArrowLeft;
    const title = t(module ? `management.${module}` : 'management.title');
    const hasRecords = Boolean(records?.data?.length);

    return <AppLayout title={title}>
        {module && <Link href="/admin" className="nx-btn-secondary mb-4"><Back aria-hidden="true" className="size-4" />{t('setup.back')}</Link>}
        <h1 className="text-2xl font-semibold">{title}</h1>
        {module ? <>
            <p className="mb-4 mt-2 text-sm nx-quiet">{t(`management.${module}_help`)}</p>
            {module === 'contacts' && <form className="nx-card mb-4 grid gap-3 p-4 sm:grid-cols-2" onSubmit={event => {
                event.preventDefault();
                form.post('/admin/contacts', { onSuccess: () => form.reset() });
            }}>
                {(['first_name', 'last_name', 'email', 'phone'] as const).map(field => <label key={field} className="grid gap-1 text-sm">
                    {t(`management.field_${field}`)}
                    <input className="nx-input" required={field === 'first_name'} name={field}
                        type={field === 'email' ? 'email' : field === 'phone' ? 'tel' : 'text'}
                        value={form.data[field]} onChange={event => form.setData(field, event.target.value)} aria-invalid={!!form.errors[field]} />
                    {form.errors[field] && <span className="text-red-300" role="alert">{t('setup.invalid_field')}</span>}
                </label>)}
                <div className="sm:col-span-2">
                    <button className="nx-btn-primary" disabled={form.processing}>{t('management.add_contact')}</button>
                    {flash?.success === 'contact_saved' && <p className="mt-2 text-emerald-300" role="status">{t('management.contact_saved')}</p>}
                </div>
            </form>}
            {draftFields.length > 0 && <DraftConfiguration key={module} module={module!} fields={draftFields}
                saved={flash?.success === 'draft_saved'} />}
            {module === 'maintenance' && <section className="nx-card grid gap-2 p-4">
                {Object.entries(checks).map(([service, ok]) => <p key={service} className="flex flex-wrap justify-between gap-2 text-sm">
                    <strong>{t(`setup.${service}`)}</strong>
                    <span className={ok ? 'text-emerald-300' : 'text-amber-300'}>{t(ok ? 'status.connected' : 'status.disconnected')}</span>
                </p>)}
                <Link href="/admin/maintenance" className="nx-btn-secondary w-fit">{t('setup.retry')}</Link>
            </section>}
            {module !== 'maintenance' && <section className="nx-card p-4">
                {!hasRecords ? <p className="text-sm nx-quiet">{t('management.empty')}</p> :
                    <ul className="grid gap-2">
                        {records?.data.map((row, index) => <li key={index} className="nx-notice grid gap-2 sm:grid-cols-2">
                            {columns.map(key => <div key={key} className="min-w-0">
                                <p className="text-xs nx-quiet">{t(`management.field_${key}`)}</p>
                                <p className="break-words text-sm"><bdi>{key === 'enabled' ? t(row[key] ? 'management.enabled' : 'management.disabled') : row[key] ?? '—'}</bdi></p>
                            </div>)}
                        </li>)}
                    </ul>}
                <div className="mt-2 flex gap-2">
                    {records?.prev_page_url && <Link className="nx-btn-secondary" href={records.prev_page_url}>{t('setup.back')}</Link>}
                    {records?.next_page_url && <Link className="nx-btn-secondary" href={records.next_page_url}>{t('setup.next')}</Link>}
                </div>
            </section>}
        </> : <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {modules.map(key => {
                const Icon = icons[key];
                return <Link key={key} href={`/admin/${key}`} className="nx-card flex items-center gap-3 p-4 text-sm font-semibold hover:border-sky-400">
                    <Icon aria-hidden="true" className="size-5 shrink-0 text-sky-300" />{t(`management.${key}`)}
                </Link>;
            })}
        </div>}
    </AppLayout>;
}

function DraftConfiguration({ module, fields, saved }: { module: Module; fields: string[]; saved: boolean }) {
    const { t } = useTranslation();
    const form = useForm<Record<string, string>>(Object.fromEntries(fields.map(field => [
        field, field === 'sip_port' ? '5060' : field === 'currency' ? 'USD' : '',
    ])));
    return <form className="nx-card mb-4 grid gap-3 p-4 sm:grid-cols-2" onSubmit={event => {
        event.preventDefault();
        form.post(`/admin/${module}/draft`, { onSuccess: () => form.reset() });
    }}>
        <p className="text-sm nx-quiet sm:col-span-2">{t('management.draft_help')}</p>
        {fields.map(field => <label key={field} className="grid gap-1 text-sm">
            {t(`management.field_${field}`)}
            <input className="nx-input" required={field !== 'description'} name={field}
                type={field === 'sip_port' ? 'number' : 'text'}
                min={field === 'sip_port' ? 1 : undefined} max={field === 'sip_port' ? 65535 : undefined}
                value={form.data[field]} onChange={event => form.setData(field, event.target.value)} aria-invalid={!!form.errors[field]} />
            {form.errors[field] && <span className="text-red-300" role="alert">{t('setup.invalid_field')}</span>}
        </label>)}
        <div className="sm:col-span-2">
            <button className="nx-btn-primary" disabled={form.processing}>{t('management.save_draft')}</button>
            {saved && <p className="mt-2 text-emerald-300" role="status">{t('management.draft_saved')}</p>}
        </div>
    </form>;
}
