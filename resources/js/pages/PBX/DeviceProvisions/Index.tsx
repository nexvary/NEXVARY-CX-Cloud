import React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Smartphone, Edit, Trash2, RefreshCw, Copy } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Extension { id: string; number: string; description: string | null; }
interface DeviceProvision {
  id: string; mac_address: string | null; device_type: string; enabled: boolean;
  last_provisioned_at: string | null; extension: Extension | null;
}
interface Props { provisions: DeviceProvision[]; }

const DeviceProvisionsIndex: React.FC<Props> = ({ provisions }) => {
  const { props } = usePage<{ flash?: { provision_token?: string } }>();
  const token = props.flash?.provision_token;

  const handleDelete = (id: string) => {
    if (!confirm('Delete this device provision?')) return;
    router.delete(`/pbx/device-provisions/${id}`);
  };
  const regenerate = (id: string) => {
    if (!confirm('Regenerate provision token? The old URL will stop working.')) return;
    router.post(`/pbx/device-provisions/${id}/regenerate-token`, {}, { preserveScroll: true });
  };

  return (
    <AppLayout title="Device Provisioning">
      <Head title="Device Provisioning" />
      {token && (
        <div className="mx-6 mt-6 p-4 rounded-lg border border-green-500/40 bg-green-500/10">
          <p className="text-sm font-medium text-green-400 mb-1">Provision URL — copy now, shown once only:</p>
          <div className="flex items-center gap-2">
            <code className="flex-1 font-mono text-xs bg-black/30 rounded px-3 py-2 text-nx-primary break-all">
              {`${window.location.origin}/provision/${token}`}
            </code>
            <button onClick={() => navigator.clipboard?.writeText(`${window.location.origin}/provision/${token}`)}
              className="nx-btn-ghost p-2" title="Copy">
              <Copy className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Device Provisioning</h1>
          <p className="nx-page-subtitle">Manage SIP phone auto-provisioning</p>
        </div>
        <Link href="/pbx/device-provisions/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> Add Device
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>MAC Address</th><th>Type</th><th>Extension</th>
                <th>Last Provisioned</th><th>Status</th><th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {provisions.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-12 text-nx-muted">
                    <Smartphone className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    <p>No devices configured.</p>
                    <Link href="/pbx/device-provisions/create" className="nx-btn-primary mt-4 inline-flex">
                      <Plus className="w-4 h-4" /> Add first device
                    </Link>
                  </td>
                </tr>
              )}
              {provisions.map(p => (
                <tr key={p.id}>
                  <td className="font-mono text-sm">{p.mac_address ?? '—'}</td>
                  <td><span className="nx-badge nx-badge-blue capitalize">{p.device_type}</span></td>
                  <td className="text-sm">
                    {p.extension
                      ? <span className="font-mono" style={{ color: 'var(--nx-green)' }}>{p.extension.number}</span>
                      : <span className="text-nx-muted">—</span>}
                  </td>
                  <td className="text-sm text-nx-muted">
                    {p.last_provisioned_at ? new Date(p.last_provisioned_at).toLocaleString() : 'Never'}
                  </td>
                  <td>
                    <span className={`nx-badge ${p.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      <span className={`nx-dot me-1.5 ${p.enabled ? 'nx-dot-green' : 'nx-dot-red'}`} />
                      {p.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <button onClick={() => regenerate(p.id)} className="nx-btn-ghost p-1.5" title="Regenerate token">
                        <RefreshCw className="w-3.5 h-3.5" />
                      </button>
                      <Link href={`/pbx/device-provisions/${p.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(p.id)} className="nx-btn-ghost p-1.5 text-red-400 hover:text-red-300">
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
};
export default DeviceProvisionsIndex;
