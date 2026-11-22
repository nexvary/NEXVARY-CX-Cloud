import { Head, Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { BookOpen, LayoutDashboard, LogOut, Menu, X } from 'lucide-react';

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { t } = useTranslation();
    const { url, props } = usePage<{ auth: { roles?: string[]; user?: { name: string } } }>();
    const [open, setOpen] = useState(false);
    const isAdmin = props.auth.roles?.some(role => role === 'admin' || role === 'superadmin');
    const items = [
        { href: '/dashboard', label: t('nav.dashboard'), Icon: LayoutDashboard },
        ...(isAdmin ? [{ href: '/admin', label: t('management.title'), Icon: BookOpen }] : []),
    ];

    return <div className="min-h-dvh bg-[#0b0d13]">
        <Head title={title} />
        <header className="border-b border-[#3b414e] bg-[#151922]">
            <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <Link href="/dashboard" className="flex items-center gap-3">
                    <span className="nx-brand-mark" aria-hidden="true">N</span>
                    <span className="text-sm font-bold tracking-wide" dir="ltr">NEXVARY CX Cloud</span>
                </Link>
                <div className="flex items-center gap-3">
                    <span className="hidden text-sm nx-quiet sm:inline">{props.auth.user?.name}</span>
                    <button type="button" className="nx-btn-secondary md:hidden" aria-controls="app-navigation" aria-expanded={open}
                        onClick={() => setOpen(!open)} aria-label={t('management.navigation')}>
                        {open ? <X className="size-4" /> : <Menu className="size-4" />}
                    </button>
                </div>
            </div>
        </header>
        <div className="mx-auto max-w-6xl gap-8 px-4 py-5 sm:px-6 md:grid md:grid-cols-[12rem_minmax(0,1fr)] md:py-8">
            <nav id="app-navigation" aria-label={t('management.navigation')} className={`${open ? 'grid' : 'hidden'} mb-5 content-start gap-2 md:mb-0 md:grid`}>
                {items.map(({ href, label, Icon }) => <Link key={href} href={href} aria-current={url.startsWith(href) ? 'page' : undefined}
                    className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm ${url.startsWith(href) ? 'border-[#52647a] bg-[#202c3b]' : 'border-transparent hover:bg-[#20242f]'}`}>
                    <Icon aria-hidden="true" className="size-4 shrink-0 text-sky-300" />{label}
                </Link>)}
                <Link as="button" method="post" href="/logout" className="text-start nx-btn-secondary justify-start">
                    <LogOut aria-hidden="true" className="size-4 text-sky-300" />{t('auth.logout')}
                </Link>
            </nav>
            <main className="min-w-0">{children}</main>
        </div>
    </div>;
}
