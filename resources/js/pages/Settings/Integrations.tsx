import React from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Package, CheckCircle, XCircle, ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
  telnyx_configured: boolean;
  sipProviders: { key: string; label: string; docsUrl: string }[];
  fusionPbx: boolean;
  fusionPbxAdminUrl: string | null;
}

const IntegrationsSettings: React.FC<Props> = ({ telnyx_configured, fusionPbx, fusionPbxAdminUrl, sipProviders }) => {
  const { i18n } = useTranslation();
  const ar = i18n.dir() === 'rtl';

  return (
    <AppLayout title="Integrations">
      <Head title="Integrations" />
      <div className="p-6 space-y-6">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>Integrations</h1>
          <p className="text-sm text-nx-muted mt-0.5">Connect external service providers</p>
        </div>

        {fusionPbx && (
          <section className="nx-card p-6 space-y-3">
            <h2 className="font-medium">الاتصالات الخارجية · FusionPBX</h2>
            <ul className="space-y-2 text-sm text-nx-muted">
              <li>بوابات الاتصال الخارجي — Accounts → Gateways</li>
              <li>مسارات الاتصال الصادر — Dialplan → Outbound Routes</li>
              <li>أرقام الاستقبال والوجهات — Dialplan → Destinations</li>
              <li>حسابات SIP — Accounts → Extensions</li>
            </ul>
            {fusionPbxAdminUrl ? (
              <a href={fusionPbxAdminUrl} target="_blank" rel="noopener noreferrer" className="nx-btn-primary">
                فتح لوحة FusionPBX
              </a>
            ) : (
              <p className="text-sm text-nx-muted">عنوان لوحة FusionPBX لم يُضبط في Nexvary.</p>
            )}
          </section>
        )}

        <div className="nx-card p-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-500/10">
                <Package className="w-5 h-5 text-blue-400" />
              </div>
              <div>
                <p className="font-medium" style={{ color: 'var(--nx-silver)' }}>Telnyx</p>
                <p className="text-sm text-nx-muted">Cloud communications — SMS, voice trunks</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              {telnyx_configured ? (
                <span className="flex items-center gap-1.5 text-sm text-green-400">
                  <CheckCircle className="w-4 h-4" /> Configured
                </span>
              ) : (
                <span className="flex items-center gap-1.5 text-sm text-nx-muted">
                  <XCircle className="w-4 h-4" /> Not configured
                </span>
              )}
              <a href="https://portal.telnyx.com" target="_blank" rel="noopener noreferrer"
                 className="nx-btn-primary gap-1 text-sm">
                <ExternalLink className="w-3.5 h-3.5" /> Open Telnyx Portal
              </a>
            </div>
          </div>
        </div>
        <section className="nx-card p-6 space-y-4" aria-labelledby="sip-provider-catalog">
          <div>
            <h2 id="sip-provider-catalog" className="font-medium">
              {ar ? 'كتالوج مزوّدي SIP' : 'SIP provider catalog'} ({sipProviders.length})
            </h2>
            <p className="text-sm text-nx-muted mt-1">
              {ar
                ? 'قوالب إعداد. حالة اتصال البوابات تُراجع في لوحة إدارة السنترال.'
                : 'Configuration presets. Check gateway connection status in your PBX administration console.'}
            </p>
          </div>
          <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
            {sipProviders.map(provider => (
              <li key={provider.key} className="rounded-lg border border-[var(--nx-border)] p-3">
                <a href={provider.docsUrl} target="_blank" rel="noopener noreferrer"
                   className="flex items-center justify-between gap-3 text-sm hover:underline"
                   aria-label={ar ? `وثائق ${provider.label}` : `${provider.label} documentation`}>
                  <span>{provider.label}</span>
                  <ExternalLink className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                </a>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </AppLayout>
  );
};

export default IntegrationsSettings;
