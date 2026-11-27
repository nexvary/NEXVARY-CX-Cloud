import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Settings, Edit, Trash2, Zap, ZapOff } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface SipProfile {
  id: string; name: string; type: string; sip_port: number | null;
  sip_ip: string | null; nat_enabled: boolean; tls_enabled: boolean;
  srtp_mode: string; enabled: boolean;
}
interface Props { profiles: SipProfile[]; }

const SipProfilesIndex: React.FC<Props> = ({ profiles }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete SIP profile "${name}"?`)) return;
    router.delete(`/pbx/sip-profiles/${id}`);
  };
  const activate = (id: string) =>
    router.post(`/pbx/sip-profiles/${id}/activate`, {}, { preserveScroll: true });
  const deactivate = (id: string) =>
    router.post(`/pbx/sip-profiles/${id}/deactivate`, {}, { preserveScroll: true });

  return (
    <AppLayout title="SIP Profiles">
      <Head title="SIP Profiles" />
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">SIP Profiles</h1>
          <p className="nx-page-subtitle">Manage FreeSWITCH sofia profiles</p>
        </div>
        <Link href="/pbx/sip-profiles/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New Profile
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Name</th><th>Type</th><th>SIP Port</th><th>IP</th>
                <th>Flags</th><th>Status</th><th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {profiles.length === 0 && (
                <tr>
                  <td colSpan={7} className="text-center py-12 text-nx-muted">
                    <Settings className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    <p>No SIP profiles configured.</p>
                    <Link href="/pbx/sip-profiles/create" className="nx-btn-primary mt-4 inline-flex">
                      <Plus className="w-4 h-4" /> Create first profile
                    </Link>
                  </td>
                </tr>
              )}
              {profiles.map(p => (
                <tr key={p.id}>
                  <td className="font-medium text-nx-primary">{p.name}</td>
                  <td><span className={`nx-badge ${p.type === 'internal' ? 'nx-badge-blue' : 'nx-badge-yellow'}`}>{p.type}</span></td>
                  <td className="font-mono text-sm">{p.sip_port ?? 5060}</td>
                  <td className="font-mono text-sm text-nx-muted">{p.sip_ip ?? 'auto'}</td>
                  <td>
                    <div className="flex gap-1">
                      {p.nat_enabled && <span className="nx-badge nx-badge-blue">NAT</span>}
                      {p.tls_enabled && <span className="nx-badge nx-badge-green">TLS</span>}
                      {p.srtp_mode !== 'disabled' && <span className="nx-badge nx-badge-green">SRTP</span>}
                    </div>
                  </td>
                  <td>
                    <span className={`nx-badge ${p.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      <span className={`nx-dot me-1.5 ${p.enabled ? 'nx-dot-green' : 'nx-dot-red'}`} />
                      {p.enabled ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      {p.enabled ? (
                        <button onClick={() => deactivate(p.id)} className="nx-btn-ghost p-1.5" title="Deactivate">
                          <ZapOff className="w-3.5 h-3.5 text-yellow-400" />
                        </button>
                      ) : (
                        <button onClick={() => activate(p.id)} className="nx-btn-ghost p-1.5" title="Activate">
                          <Zap className="w-3.5 h-3.5 text-green-400" />
                        </button>
                      )}
                      <Link href={`/pbx/sip-profiles/${p.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(p.id, p.name)} className="nx-btn-ghost p-1.5 text-red-400 hover:text-red-300">
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
export default SipProfilesIndex;
