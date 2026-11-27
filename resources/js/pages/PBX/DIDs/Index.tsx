import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Hash, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Did {
  id: string; number: string; description?: string;
  sms_enabled: boolean; voice_enabled: boolean; enabled: boolean;
}
interface Props { dids: { data: Did[]; total: number } }

const DidsIndex: React.FC<Props> = ({ dids }) => {
  const handleDelete = (id: string, number: string) => {
    if (!confirm(`Delete DID ${number}?`)) return;
    router.delete(`/pbx/dids/${id}`);
  };

  return (
    <AppLayout title="DID Numbers">
      <Head title="DID Numbers" />
      <div className="p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>DID Numbers</h1>
            <p className="text-sm text-nx-muted mt-0.5">{dids.total} numbers configured</p>
          </div>
          <Link href="/pbx/dids/create" className="nx-btn-primary gap-2">
            <Plus className="w-4 h-4" /> Add DID
          </Link>
        </div>

        <div className="nx-card overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-nx text-nx-muted text-xs uppercase tracking-wider">
                <th className="text-start px-4 py-3">Number</th>
                <th className="text-start px-4 py-3">Description</th>
                <th className="text-start px-4 py-3">Voice</th>
                <th className="text-start px-4 py-3">SMS</th>
                <th className="text-start px-4 py-3">Status</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-nx">
              {dids.data.map(did => (
                <tr key={did.id} className="hover:bg-white/[0.02] transition-colors">
                  <td className="px-4 py-3 font-mono" style={{ color: 'var(--nx-silver)' }}>
                    <div className="flex items-center gap-2">
                      <Hash className="w-4 h-4 text-nx-primary shrink-0" />
                      {did.number}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-nx-muted">{did.description || '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`nx-badge ${did.voice_enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {did.voice_enabled ? 'Yes' : 'No'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`nx-badge ${did.sms_enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {did.sms_enabled ? 'Yes' : 'No'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`nx-badge ${did.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {did.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 justify-end">
                      <Link href={`/pbx/dids/${did.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-4 h-4" />
                      </Link>
                      <button onClick={() => handleDelete(did.id, did.number)} className="nx-btn-ghost p-1.5 text-red-400">
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {dids.data.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-nx-muted">
                    No DID numbers configured yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
};

export default DidsIndex;
