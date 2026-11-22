import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, Server } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Trunk {
  id?: string;
  name?: string;
  provider?: string;
  username?: string;
  sip_server?: string;
  sip_port?: number;
  register?: boolean;
  enabled?: boolean;
}

interface Props {
  trunk?: Trunk;
}

const TrunkForm: React.FC<Props> = ({ trunk }) => {
  const isEdit = Boolean(trunk?.id);

  const { data, setData, post, patch, processing, errors } = useForm({
    name:         trunk?.name ?? '',
    provider:     trunk?.provider ?? '',
    username:     trunk?.username ?? '',
    sip_password: '',
    sip_server:   trunk?.sip_server ?? '',
    sip_port:     trunk?.sip_port ?? 5060,
    register:     trunk?.register ?? true,
    enabled:      trunk?.enabled ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      patch(`/pbx/trunks/${trunk!.id}`);
    } else {
      post('/pbx/trunks');
    }
  };

  return (
    <AppLayout title={isEdit ? 'Edit Trunk' : 'New SIP Trunk'}>
      <Head title={isEdit ? 'Edit Trunk' : 'New SIP Trunk'} />

      <div className="nx-page-header flex items-center gap-3">
        <Link href="/pbx/trunks" className="nx-btn-ghost p-1.5">
          <ArrowLeft className="w-4 h-4" />
        </Link>
        <div>
          <h1 className="nx-page-title">{isEdit ? `Edit: ${trunk?.name}` : 'New SIP Trunk'}</h1>
          <p className="nx-page-subtitle">Configure SIP gateway settings</p>
        </div>
      </div>

      <div className="p-6 max-w-2xl nx-animate-in">
        <form onSubmit={submit} className="space-y-6">
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Server className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
              Trunk Identity
            </h2>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Trunk Name *</label>
                <input
                  className={`nx-input ${errors.name ? 'error' : ''}`}
                  value={data.name}
                  onChange={e => setData('name', e.target.value)}
                  placeholder="e.g. Telnyx-Primary"
                />
                {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
              </div>
              <div>
                <label className="nx-label">Provider</label>
                <input
                  className="nx-input"
                  value={data.provider}
                  onChange={e => setData('provider', e.target.value)}
                  placeholder="e.g. Telnyx, Twilio, Vonage"
                />
              </div>
            </div>
          </div>

          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary">SIP Credentials</h2>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">SIP Username *</label>
                <input
                  className={`nx-input ${errors.username ? 'error' : ''}`}
                  value={data.username}
                  onChange={e => setData('username', e.target.value)}
                  placeholder="SIP registration username"
                />
              </div>
              <div>
                <label className="nx-label">
                  SIP Password {isEdit ? '(leave blank to keep current)' : '*'}
                </label>
                <input
                  type="password"
                  className="nx-input"
                  value={data.sip_password}
                  onChange={e => setData('sip_password', e.target.value)}
                  placeholder={isEdit ? '••••••••••••' : 'SIP authentication password'}
                  autoComplete="new-password"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">SIP Server / Proxy *</label>
                <input
                  className={`nx-input ${errors.sip_server ? 'error' : ''}`}
                  value={data.sip_server}
                  onChange={e => setData('sip_server', e.target.value)}
                  placeholder="sip.provider.com"
                />
              </div>
              <div>
                <label className="nx-label">SIP Port</label>
                <input
                  type="number"
                  className="nx-input"
                  value={data.sip_port}
                  onChange={e => setData('sip_port', parseInt(e.target.value))}
                  min={1}
                  max={65535}
                />
              </div>
            </div>
          </div>

          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary">Options</h2>
            <div className="flex items-center gap-6">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.register}
                  onChange={e => setData('register', e.target.checked)}
                  className="rounded"
                />
                <span className="text-sm text-nx-secondary">Register with provider</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.enabled}
                  onChange={e => setData('enabled', e.target.checked)}
                  className="rounded"
                />
                <span className="text-sm text-nx-secondary">Enabled</span>
              </label>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing} className="nx-btn-primary">
              {processing ? <span className="nx-spinner" /> : <Save className="w-4 h-4" />}
              {isEdit ? 'Update Trunk' : 'Create Trunk'}
            </button>
            <Link href="/pbx/trunks" className="nx-btn-secondary">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};

export default TrunkForm;
