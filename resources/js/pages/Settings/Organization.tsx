import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Building2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface OrgProps {
  organization?: {
    name?: string;
    timezone?: string;
    locale?: string;
    country?: string;
  };
}

const OrganizationSettings: React.FC<OrgProps> = ({ organization = {} }) => {
  const { data, setData, post, processing } = useForm({
    name:     organization.name     ?? '',
    country:  organization.country  ?? '',
    locale:   organization.locale   ?? 'en',
    timezone: organization.timezone ?? 'UTC',
  });

  return (
    <AppLayout title="Organization Settings">
      <Head title="Organization Settings" />

      <div className="nx-page-header">
        <h1 className="nx-page-title">Organization Settings</h1>
        <p className="nx-page-subtitle">Configure your organization profile and defaults</p>
      </div>

      <div className="p-6 max-w-2xl nx-animate-in">
        <form onSubmit={e => { e.preventDefault(); post('/settings/organization'); }} className="space-y-6">
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Building2 className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
              Organization Profile
            </h2>
            <div>
              <label className="nx-label">Organization Name *</label>
              <input className="nx-input" value={data.name} onChange={e => setData('name', e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Primary Domain</label>
                <input
                  className="nx-input"
                  value={data.domain}
                  onChange={e => setData('domain', e.target.value)}
                  placeholder="pbx.yourdomain.com"
                />
              </div>
              <div>
                <label className="nx-label">Country</label>
                <input
                  className="nx-input"
                  value={data.country}
                  onChange={e => setData('country', e.target.value)}
                  placeholder="US"
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Default Language</label>
                <select className="nx-select" value={data.locale} onChange={e => setData('locale', e.target.value)}>
                  <option value="en">English</option>
                  <option value="ar">العربية</option>
                  <option value="es">Español</option>
                  <option value="fr">Français</option>
                  <option value="de">Deutsch</option>
                  <option value="tr">Türkçe</option>
                  <option value="fa">فارسی</option>
                </select>
              </div>
              <div>
                <label className="nx-label">Timezone</label>
                <input className="nx-input" value={data.timezone} onChange={e => setData('timezone', e.target.value)} placeholder="UTC" />
              </div>
            </div>
          </div>

          <button type="submit" disabled={processing} className="nx-btn-primary">
            {processing ? <span className="nx-spinner" /> : <Save className="w-4 h-4" />}
            Save Settings
          </button>
        </form>
      </div>
    </AppLayout>
  );
};

export default OrganizationSettings;
