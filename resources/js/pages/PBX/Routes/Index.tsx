import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, GitBranch, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Route { id: string; name: string; did?: string; dial_pattern?: string; destination?: string; priority: number; enabled: boolean }
interface Props { type: 'inbound' | 'outbound'; routes: Route[] }

const RoutesIndex: React.FC<Props> = ({ type, routes }) => {
  const isInbound = type === 'inbound';
  const prefix = isInbound ? '/pbx/inbound-routes' : '/pbx/outbound-routes';

  const handleDelete = (id: string) => {
    if (!confirm('Delete this route?')) return;
    router.delete(`${prefix}/${id}`);
  };

  return (
    <AppLayout title={isInbound ? 'Inbound Routes' : 'Outbound Routes'}>
      <Head title={isInbound ? 'Inbound Routes' : 'Outbound Routes'} />
      <div className="p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>
              {isInbound ? 'Inbound Routes' : 'Outbound Routes'}
            </h1>
            <p className="text-sm text-nx-muted mt-0.5">{routes.length} routes</p>
          </div>
          <Link href={`${prefix}/create`} className="nx-btn-primary gap-2">
            <Plus className="w-4 h-4" /> New Route
          </Link>
        </div>

        <div className="nx-card overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-nx text-nx-muted text-xs uppercase tracking-wider">
                <th className="text-start px-4 py-3">Name</th>
                <th className="text-start px-4 py-3">{isInbound ? 'DID' : 'Dial Pattern'}</th>
                <th className="text-start px-4 py-3">Destination</th>
                <th className="text-start px-4 py-3">Priority</th>
                <th className="text-start px-4 py-3">Status</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-nx">
              {routes.map(route => (
                <tr key={route.id} className="hover:bg-white/[0.02] transition-colors">
                  <td className="px-4 py-3 font-medium" style={{ color: 'var(--nx-silver)' }}>
                    <div className="flex items-center gap-2">
                      <GitBranch className="w-4 h-4 text-nx-primary shrink-0" />
                      {route.name}
                    </div>
                  </td>
                  <td className="px-4 py-3 font-mono text-nx-muted">
                    {isInbound ? (route.did || 'Any') : (route.dial_pattern || '—')}
                  </td>
                  <td className="px-4 py-3 text-nx-muted">{route.destination || '—'}</td>
                  <td className="px-4 py-3 text-nx-muted">{route.priority}</td>
                  <td className="px-4 py-3">
                    <span className={`nx-badge ${route.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {route.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 justify-end">
                      <Link href={`${prefix}/${route.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-4 h-4" />
                      </Link>
                      <button onClick={() => handleDelete(route.id)} className="nx-btn-ghost p-1.5 text-red-400">
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {routes.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-nx-muted">
                    No {isInbound ? 'inbound' : 'outbound'} routes configured.
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

export default RoutesIndex;
