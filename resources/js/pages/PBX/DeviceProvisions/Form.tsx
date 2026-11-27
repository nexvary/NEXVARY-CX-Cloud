import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, Smartphone } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Extension { id: string; number: string; description: string | null; }
interface DeviceProvision {
  id?: string; extension_id?: string; mac_address?: string;
  device_type?: string; enabled?: boolean;
}
interface Props { provision?: DeviceProvision; extensions: Extension[]; deviceTypes: string[]; }

const DeviceProvisionForm: React.FC<Props> = ({ provision, extensions, deviceTypes }) => {
  const isEdit = Boolean(provision?.id);
  const { data, setData, post, patch, processing, errors } = useForm({
    extension_id: provision?.extension_id ?? '',
    mac_address:  provision?.mac_address ?? '',
    device_type:  provision?.device_type ?? 'generic',
    enabled:      provision?.enabled ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) patch(`/pbx/device-provisions/${provision!.id}`);
    else post('/pbx/device-provisions');
  };

  return (
    <AppLayout title={isEdit ? 'Edit Device' : 'Add Device'}>
      <Head title={isEdit ? 'Edit Device' : 'Add Device'} />
      <div className="nx-page-header flex items-center gap-3">
        <Link href="/pbx/device-provisions" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
        <div>
          <h1 className="nx-page-title">{isEdit ? 'Edit Device' : 'Add Device'}</h1>
          <p className="nx-page-subtitle">Configure SIP phone auto-provisioning</p>
        </div>
      </div>
      <div className="p-6 max-w-2xl nx-animate-in">
        <form onSubmit={submit} className="space-y-6">
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Smartphone className="w-4 h-4" style={{ color: 'var(--nx-green)' }} /> Device Settings
            </h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Device Type *</label>
                <select className="nx-input" value={data.device_type} onChange={e => setData('device_type', e.target.value)}>
                  {deviceTypes.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <div>
                <label className="nx-label">MAC Address</label>
                <input className={`nx-input font-mono ${errors.mac_address ? 'error' : ''}`}
                  value={data.mac_address} onChange={e => setData('mac_address', e.target.value)}
                  placeholder="AA:BB:CC:DD:EE:FF" />
                {errors.mac_address && <p className="text-xs text-red-400 mt-1">{errors.mac_address}</p>}
              </div>
              <div className="col-span-2">
                <label className="nx-label">Extension</label>
                <select className="nx-input" value={data.extension_id} onChange={e => setData('extension_id', e.target.value)}>
                  <option value="">— Unassigned —</option>
                  {extensions.map(ext => (
                    <option key={ext.id} value={ext.id}>
                      {ext.number}{ext.description ? ` — ${ext.description}` : ''}
                    </option>
                  ))}
                </select>
              </div>
              <div className="col-span-2">
                <label className="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" className="nx-checkbox" checked={data.enabled as boolean}
                    onChange={e => setData('enabled', e.target.checked)} />
                  <span className="nx-label mb-0">Enable provisioning</span>
                </label>
              </div>
            </div>
          </div>
          <div className="flex gap-3">
            <button type="submit" disabled={processing} className="nx-btn-primary">
              <Save className="w-4 h-4" /> {processing ? 'Saving...' : (isEdit ? 'Save' : 'Add Device')}
            </button>
            <Link href="/pbx/device-provisions" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};
export default DeviceProvisionForm;
