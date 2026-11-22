import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, Phone } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Extension {
  id?: string;
  number?: string;
  name?: string;
  timeout?: number;
  context?: string;
  enabled?: boolean;
  dnd_enabled?: boolean;
  call_forward_enabled?: boolean;
  call_forward_number?: string | null;
}

interface Props {
  extension?: Extension;
}

const ExtensionForm: React.FC<Props> = ({ extension }) => {
  const isEdit = Boolean(extension?.id);

  const { data, setData, post, patch, processing, errors } = useForm({
    number:               extension?.number ?? '',
    name:                 extension?.name ?? '',
    timeout:              extension?.timeout ?? 30,
    context:              extension?.context ?? 'default',
    enabled:              extension?.enabled ?? true,
    dnd_enabled:          extension?.dnd_enabled ?? false,
    call_forward_enabled: extension?.call_forward_enabled ?? false,
    call_forward_number:  extension?.call_forward_number ?? '',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      patch(`/pbx/extensions/${extension!.id}`);
    } else {
      post('/pbx/extensions');
    }
  };

  return (
    <AppLayout title={isEdit ? 'Edit Extension' : 'New Extension'}>
      <Head title={isEdit ? 'Edit Extension' : 'New Extension'} />

      <div className="nx-page-header flex items-center gap-3">
        <Link href="/pbx/extensions" className="nx-btn-ghost p-1.5">
          <ArrowLeft className="w-4 h-4" />
        </Link>
        <div>
          <h1 className="nx-page-title">{isEdit ? `Edit: ${extension?.number}` : 'New Extension'}</h1>
          <p className="nx-page-subtitle">Configure SIP extension settings</p>
        </div>
      </div>

      <div className="p-6 max-w-2xl nx-animate-in">
        <form onSubmit={submit} className="space-y-6">
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Phone className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
              Extension Details
            </h2>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Extension Number *</label>
                <input
                  className={`nx-input font-mono ${errors.number ? 'error' : ''}`}
                  value={data.number}
                  onChange={e => setData('number', e.target.value)}
                  placeholder="e.g. 1001"
                  disabled={isEdit}
                />
                {errors.number && <p className="text-xs text-red-400 mt-1">{errors.number}</p>}
              </div>
              <div>
                <label className="nx-label">Display Name *</label>
                <input
                  className={`nx-input ${errors.name ? 'error' : ''}`}
                  value={data.name}
                  onChange={e => setData('name', e.target.value)}
                  placeholder="e.g. John Smith"
                />
                {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Ring Timeout (seconds)</label>
                <input
                  type="number"
                  className="nx-input"
                  value={data.timeout}
                  onChange={e => setData('timeout', parseInt(e.target.value))}
                  min={10}
                  max={120}
                />
              </div>
              <div>
                <label className="nx-label">Dialplan Context</label>
                <input
                  className="nx-input font-mono"
                  value={data.context}
                  onChange={e => setData('context', e.target.value)}
                  placeholder="default"
                />
              </div>
            </div>
          </div>

          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary">Call Handling</h2>

            <div className="space-y-3">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.dnd_enabled}
                  onChange={e => setData('dnd_enabled', e.target.checked)}
                />
                <span className="text-sm text-nx-secondary">Do Not Disturb</span>
              </label>

              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.call_forward_enabled}
                  onChange={e => setData('call_forward_enabled', e.target.checked)}
                />
                <span className="text-sm text-nx-secondary">Enable Call Forwarding</span>
              </label>

              {data.call_forward_enabled && (
                <div className="ms-6">
                  <label className="nx-label">Forward to Number</label>
                  <input
                    className="nx-input font-mono"
                    value={data.call_forward_number}
                    onChange={e => setData('call_forward_number', e.target.value)}
                    placeholder="e.g. 1002 or +1234567890"
                  />
                </div>
              )}

              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.enabled}
                  onChange={e => setData('enabled', e.target.checked)}
                />
                <span className="text-sm text-nx-secondary">Extension Active</span>
              </label>
            </div>
          </div>

          {!isEdit && (
            <div className="nx-card p-4 border-green-500/20 bg-green-500/5">
              <p className="text-xs text-green-400">
                A random SIP password and voicemail PIN will be generated automatically.
              </p>
            </div>
          )}

          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing} className="nx-btn-primary">
              {processing ? <span className="nx-spinner" /> : <Save className="w-4 h-4" />}
              {isEdit ? 'Update Extension' : 'Create Extension'}
            </button>
            <Link href="/pbx/extensions" className="nx-btn-secondary">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};

export default ExtensionForm;
