import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  LayoutDashboard, Phone, Users, MessageSquare, Mail, Ticket,
  CreditCard, BarChart3, Settings, Shield, Server, ChevronDown,
  Bell, Search, LogOut, User, Mic, PhoneCall,
  Menu, X, Radio, Voicemail, GitBranch, Layers, Building2,
  Bot, FileText, Package, Hash, HelpCircle, Info, Code2
} from 'lucide-react';

interface PageProps {
  auth: {
    user: { id?: string; name: string; email?: string; role?: string };
    roles?: string[];
  };
  pbxStatus?: {
    connected: boolean;
    active_calls: number;
    version?: string;
  };
  unread?: {
    sms: number;
    email: number;
    tickets: number;
  };
  flash?: {
    success?: string;
    error?: string;
  };
  [key: string]: unknown;
}

interface NavItem {
  label: string;
  href: string;
  icon: React.ElementType;
  badge?: number;
  roles?: string[];
  children?: NavItem[];
}

// ── Navigation Config ─────────────────────────────────────────────────────

const useNavItems = (unread: { sms: number; email: number; tickets: number }): NavItem[] => {
  const { t } = useTranslation();
  return [
    {
      label: t('nav.dashboard'),
      href: '/dashboard',
      icon: LayoutDashboard,
    },
    {
      label: t('nav.administration'),
      href: '/admin',
      icon: Shield,
    },
    {
      label: t('nav.pbx'),
      href: '#pbx',
      icon: Phone,
      children: [
        { label: t('nav.extensions'), href: '/pbx/extensions', icon: Phone },
        { label: t('nav.trunks'), href: '/pbx/trunks', icon: Server },
        { label: t('nav.did_numbers'), href: '/pbx/dids', icon: Hash },
        { label: t('nav.inbound_routes'), href: '/pbx/inbound-routes', icon: GitBranch },
        { label: t('nav.outbound_routes'), href: '/pbx/outbound-routes', icon: GitBranch },
        { label: t('nav.ivr'), href: '/pbx/ivr', icon: Layers },
        { label: t('nav.call_queues'), href: '/pbx/queues', icon: Users },
        { label: t('nav.ring_groups'), href: '/pbx/ring-groups', icon: Radio },
        { label: t('nav.voicemail'), href: '/pbx/voicemail', icon: Voicemail },
      ],
    },
    {
      label: t('nav.call_center'),
      href: '#callcenter',
      icon: Mic,
      children: [
        { label: t('nav.live_calls'), href: '/call-center/live', icon: PhoneCall },
        { label: t('nav.call_records'), href: '/call-center/records', icon: FileText },
        { label: t('nav.recordings'), href: '/call-center/recordings', icon: Mic },
        { label: t('nav.agents'), href: '/call-center/agents', icon: Users },
      ],
    },
    {
      label: t('nav.sms'),
      href: '/sms',
      icon: MessageSquare,
      badge: unread.sms,
    },
    {
      label: t('nav.email'),
      href: '/email',
      icon: Mail,
      badge: unread.email,
    },
    {
      label: t('nav.crm'),
      href: '#crm',
      icon: Building2,
      children: [
        { label: t('nav.contacts'), href: '/crm/contacts', icon: Users },
        { label: t('nav.tickets'), href: '/crm/tickets', icon: Ticket, badge: unread.tickets },
      ],
    },
    {
      label: t('nav.billing'),
      href: '#billing',
      icon: CreditCard,
      roles: ['superadmin', 'admin'],
      children: [
        { label: t('nav.accounts'), href: '/billing/accounts', icon: CreditCard },
        { label: t('nav.rate_tables'), href: '/billing/rates', icon: BarChart3 },
        { label: t('nav.invoices'), href: '/billing/invoices', icon: FileText },
        { label: t('nav.reports'), href: '/billing/reports', icon: BarChart3 },
      ],
    },
    {
      label: t('nav.voice_ai'),
      href: '/voice-ai',
      icon: Bot,
      roles: ['superadmin', 'admin'],
    },
    {
      label: t('nav.reports'),
      href: '/reports',
      icon: BarChart3,
    },
    {
      label: t('nav.settings'),
      href: '#settings',
      icon: Settings,
      roles: ['superadmin', 'admin'],
      children: [
        { label: t('nav.organization'), href: '/settings/organization', icon: Building2 },
        { label: t('nav.users'), href: '/settings/users', icon: Users },
        { label: t('nav.security'), href: '/settings/security', icon: Shield },
        { label: t('nav.server'), href: '/settings/server', icon: Server },
        { label: t('nav.integrations'), href: '/settings/integrations', icon: Package },
        { label: t('nav.backup'), href: '/settings/backup', icon: FileText },
      ],
    },
    {
      label: t('nav.info'),
      href: '#info',
      icon: Info,
      children: [
        { label: t('nav.about_system'), href: '/info/about', icon: Info },
        { label: t('nav.help_faq'), href: '/info/help', icon: HelpCircle },
        { label: t('nav.about_developer'), href: '/info/developer', icon: Code2 },
      ],
    },
  ];
};

// ── Sidebar NavItem Component ─────────────────────────────────────────────

const NavItemComp: React.FC<{ item: NavItem; depth?: number }> = ({ item, depth = 0 }) => {
  const { url } = usePage();
  const [expanded, setExpanded] = useState(() =>
    item.children?.some(c => url.startsWith(c.href)) ?? false
  );

  const isActive = !item.children && url.startsWith(item.href);
  const hasChildren = (item.children?.length ?? 0) > 0;
  const Icon = item.icon;

  if (hasChildren) {
    return (
      <div>
        <button
          onClick={() => setExpanded(!expanded)}
          className={`nx-nav-item w-full text-start ${expanded ? 'text-nx-primary' : ''}`}
          style={{ paddingInlineStart: depth > 0 ? '2.5rem' : undefined }}
        >
          <Icon className="w-4 h-4 shrink-0" />
          <span className="flex-1">{item.label}</span>
          <ChevronDown
            className={`w-3.5 h-3.5 transition-transform ${expanded ? 'rotate-180' : ''}`}
          />
        </button>
        {expanded && (
          <div className="mt-0.5">
            {item.children!.map(child => (
              <NavItemComp key={child.href} item={child} depth={depth + 1} />
            ))}
          </div>
        )}
      </div>
    );
  }

  return (
    <Link
      href={item.href}
      className={`nx-nav-item ${isActive ? 'active' : ''}`}
      style={{ paddingInlineStart: depth > 0 ? '2.5rem' : undefined }}
    >
      <Icon className="w-4 h-4 shrink-0" />
      <span className="flex-1">{item.label}</span>
      {item.badge && item.badge > 0 ? (
        <span className="nx-badge nx-badge-red text-xs px-1.5 py-0 min-w-[18px] text-center">
          {item.badge > 99 ? '99+' : item.badge}
        </span>
      ) : null}
    </Link>
  );
};


// ── PBX Status Chip ───────────────────────────────────────────────────────

const PbxStatusChip: React.FC<{ status: NonNullable<PageProps['pbxStatus']> }> = ({ status }) => {
  const { t } = useTranslation();
  if (!status.connected) {
    return (
      <div className="flex items-center gap-1.5 text-xs px-2 py-1 rounded-full bg-red-500/10 text-red-400">
        <span className="nx-dot nx-dot-red" />
        {t('status.pbx_down')}
      </div>
    );
  }
  return (
    <div className="flex items-center gap-3 text-xs">
      <div className="flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-500/10 text-green-400">
        <span className="nx-dot nx-dot-green" />
        {t('status.pbx_up')}
      </div>
      {status.active_calls > 0 && (
        <span className="nx-badge nx-badge-blue">
          <PhoneCall className="w-3 h-3 me-1" />
          {status.active_calls}
        </span>
      )}
    </div>
  );
};

// ── Main Layout ───────────────────────────────────────────────────────────

interface AppLayoutProps {
  children: React.ReactNode;
  title?: string;
}

const AppLayout: React.FC<AppLayoutProps> = ({ children, title }) => {
  const { t, i18n } = useTranslation();
  const rawProps = usePage().props as unknown as PageProps;
  const { auth, flash } = rawProps;
  const pbxStatus = rawProps.pbxStatus ?? { connected: false, active_calls: 0 };
  const unread = rawProps.unread ?? { sms: 0, email: 0, tickets: 0 };
  const navItems = useNavItems(unread);
  const [sidebarOpen, setSidebarOpen] = useState(() =>
    typeof window !== 'undefined' ? window.innerWidth >= 768 : true
  );
  const [userMenuOpen, setUserMenuOpen] = useState(false);

  // Set document direction from locale
  useEffect(() => {
    const rtlLocales = ['ar', 'fa', 'ur', 'he'];
    document.documentElement.dir = rtlLocales.includes(i18n.language) ? 'rtl' : 'ltr';
    document.documentElement.lang = i18n.language;
  }, [i18n.language]);

  const languages = [
    { code: 'ar', label: 'العربية' },
    { code: 'en', label: 'English' },
    { code: 'tr', label: 'Türkçe' },
    { code: 'es', label: 'Español' },
    { code: 'fr', label: 'Français' },
    { code: 'de', label: 'Deutsch' },
    { code: 'fa', label: 'فارسی' },
    { code: 'ur', label: 'اردو' },
  ];

  return (
    <div className="min-h-screen bg-nx-base">
      {title && <Head title={title} />}
      {/* ── Header ──────────────────────────────────────────────── */}
      <header className="nx-app-header flex items-center px-4 gap-4">
        {/* Logo + Toggle */}
        <div className="flex items-center gap-3 w-56">
          <button
            onClick={() => setSidebarOpen(!sidebarOpen)}
            className="nx-btn-ghost p-1.5 -ms-1.5"
            aria-controls="app-navigation"
            aria-expanded={sidebarOpen}
          >
            {sidebarOpen ? <X className="w-4 h-4" /> : <Menu className="w-4 h-4" />}
          </button>
          <Link href="/dashboard" className="flex items-center gap-2.5">
            <div
              className="w-7 h-7 rounded-lg flex items-center justify-center text-black text-xs font-black"
              style={{ background: 'var(--nx-green)', boxShadow: 'var(--nx-glow-green)' }}
            >
              N
            </div>
            <span className="font-semibold text-sm tracking-wide" style={{ color: 'var(--nx-silver)' }}>
              NEXVARY <span style={{ color: 'var(--nx-green)' }}>CX</span>
            </span>
          </Link>
        </div>

        {/* PBX Status */}
        <div className="hidden md:flex">
          <PbxStatusChip status={pbxStatus} />
        </div>

        {/* Spacer */}
        <div className="flex-1" />

        {/* Search */}
        <button
          className="nx-btn-ghost gap-2 text-sm hidden md:flex"
        >
          <Search className="w-4 h-4" />
          <span className="text-nx-muted text-xs">{t('common.search')}…</span>
          <kbd className="text-xs px-1.5 py-0.5 rounded border border-nx bg-nx-elevated text-nx-muted">
            /
          </kbd>
        </button>

        {/* Notifications */}
        <button className="nx-btn-ghost p-2 relative">
          <Bell className="w-4 h-4" />
          {(unread.sms + unread.email + unread.tickets) > 0 && (
            <span
              className="absolute top-1 end-1 w-2 h-2 rounded-full"
              style={{ background: 'var(--nx-error)' }}
            />
          )}
        </button>

        {/* Language */}
        <div className="relative">
          <select
            className="nx-btn-ghost gap-1.5 text-sm text-xs bg-transparent border-0 cursor-pointer"
            value={i18n.language}
            onChange={(e) => i18n.changeLanguage(e.target.value)}
            aria-label={t('common.language')}
          >
            {languages.map(lang => (
              <option key={lang.code} value={lang.code}>{lang.label}</option>
            ))}
          </select>
        </div>

        {/* User Menu */}
        <div className="relative">
          <button
            onClick={() => setUserMenuOpen(!userMenuOpen)}
            className="flex items-center gap-2 px-2 py-1.5 rounded-lg transition-colors hover:bg-nx-card"
          >
            <div
              className="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold"
              style={{ background: 'var(--nx-crimson-muted)', color: 'var(--nx-crimson-light)' }}
            >
              {auth.user.name.charAt(0).toUpperCase()}
            </div>
            <div className="hidden md:block text-start">
              <div className="text-xs font-medium text-nx-primary leading-tight">{auth.user.name}</div>
              <div className="text-xs text-nx-muted capitalize">{auth.roles?.[0] ?? auth.user.role ?? ''}</div>
            </div>
            <ChevronDown className="w-3.5 h-3.5 text-nx-muted hidden md:block" />
          </button>

          {userMenuOpen && (
            <div
              className="absolute end-0 top-full mt-1 w-48 py-1 rounded-lg border border-nx shadow-xl z-50"
              style={{ background: 'var(--nx-bg-modal)' }}
            >
              <Link
                href="/profile"
                className="flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-nx-card text-nx-secondary hover:text-nx-primary"
              >
                <User className="w-4 h-4" /> {t('nav.profile')}
              </Link>
              <Link
                href="/logout"
                method="post"
                as="button"
                className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-400 hover:bg-nx-card"
              >
                <LogOut className="w-4 h-4" /> {t('auth.logout')}
              </Link>
            </div>
          )}
        </div>
      </header>

      {/* ── Sidebar ─────────────────────────────────────────────── */}
      <aside
        id="app-navigation"
        className={`nx-sidebar overflow-y-auto transition-transform ${sidebarOpen ? '' : '-translate-x-full rtl:translate-x-full'}`}
      >
        <nav className="py-3">
          {navItems.map(item => (
            <NavItemComp key={item.href} item={item} />
          ))}
        </nav>

        {/* Version + Logout */}
        <div className="px-4 pb-4 mt-4 border-t border-nx pt-4">
          <div className="text-xs text-nx-muted mb-3">NEXVARY CX Cloud v1.0</div>
          <Link
            href="/logout"
            method="post"
            as="button"
            data-action="logout"
            className="nx-nav-item text-red-400 w-full text-start"
          >
            <LogOut className="w-4 h-4 shrink-0" />
            <span className="flex-1">{t('auth.logout')}</span>
          </Link>
        </div>
      </aside>

      {/* ── Main Content ─────────────────────────────────────────── */}
      <main className={`${sidebarOpen ? 'md:ms-60' : ''} pt-14 min-h-screen transition-all`}>
        {/* Flash Messages */}
        {flash?.success && (
          <div className="mx-6 mt-4 p-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm flex items-center gap-2">
            <span className="nx-dot nx-dot-green" />
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="mx-6 mt-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm flex items-center gap-2">
            <span className="nx-dot nx-dot-red" />
            {flash.error}
          </div>
        )}

        {children}
      </main>
    </div>
  );
};

export default AppLayout;
