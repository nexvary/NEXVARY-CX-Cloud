import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, GitBranch, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface DialplanRule {
  id: string; name: string; context: string | null; priority: number;
  continue: boolean; enabled: boolean; conditions: unknown[]; actions: unknown[];
}
interface Props { rules: DialplanRule[]; }

const DialplanRulesIndex: React.FC<Props> = ({ rules }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete rule "${name}"?`)) return;
    router.delete(`/pbx/dialplan-rules/${id}`);
  };

  return (
    <AppLayout title="Dialplan Rules">
      <Head title="Dialplan Rules" />
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Dialplan Rules</h1>
          <p className="nx-page-subtitle">Manage FreeSWITCH dialplan extensions</p>
        </div>
        <Link href="/pbx/dialplan-rules/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New Rule
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Priority</th><th>Name</th><th>Context</th>
                <th>Conditions</th><th>Actions</th><th>Continue</th>
                <th>Status</th><th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rules.length === 0 && (
                <tr>
                  <td colSpan={8} className="text-center py-12 text-nx-muted">
                    <GitBranch className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    <p>No dialplan rules configured.</p>
                    <Link href="/pbx/dialplan-rules/create" className="nx-btn-primary mt-4 inline-flex">
                      <Plus className="w-4 h-4" /> Create first rule
                    </Link>
                  </td>
                </tr>
              )}
              {rules.map(r => (
                <tr key={r.id}>
                  <td className="font-mono text-sm text-center">{r.priority}</td>
                  <td className="font-medium text-nx-primary">{r.name}</td>
                  <td className="font-mono text-sm text-nx-muted">{r.context ?? 'default'}</td>
                  <td className="text-sm text-nx-muted">{(r.conditions as unknown[]).length}</td>
                  <td className="text-sm text-nx-muted">{(r.actions as unknown[]).length}</td>
                  <td><span className={`nx-badge ${r.continue ? 'nx-badge-blue' : 'nx-badge-yellow'}`}>{r.continue ? 'Yes' : 'No'}</span></td>
                  <td>
                    <span className={`nx-badge ${r.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      <span className={`nx-dot me-1.5 ${r.enabled ? 'nx-dot-green' : 'nx-dot-red'}`} />
                      {r.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/pbx/dialplan-rules/${r.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(r.id, r.name)} className="nx-btn-ghost p-1.5 text-red-400 hover:text-red-300">
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
export default DialplanRulesIndex;
