import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, BarChart3, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface RateTable {
  id: string;
  name: string;
  currency: string;
  description: string | null;
  enabled: boolean;
  rates_count: number;
}

interface Props {
  tables: { data: RateTable[]; total: number };
}

const RatesIndex: React.FC<Props> = ({ tables }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete rate table "${name}"?`)) return;
    router.delete(`/billing/rates/${id}`);
  };

  return (
    <AppLayout title="Rate Tables">
      <Head title="Rate Tables" />
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Rate Tables</h1>
          <p className="nx-page-subtitle">Destination-based call pricing and rate management</p>
        </div>
        <Link href="/billing/rates/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New Rate Table
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Currency</th>
                <th>Rates</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {tables.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-nx-muted">
                    <BarChart3 className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No rate tables configured.
                  </td>
                </tr>
              )}
              {tables.data.map(t => (
                <tr key={t.id}>
                  <td className="font-medium text-nx-primary">{t.name}</td>
                  <td className="font-mono text-nx-secondary">{t.currency}</td>
                  <td className="text-nx-secondary">{t.rates_count} rates</td>
                  <td>
                    <span className={`nx-badge ${t.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {t.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/billing/rates/${t.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(t.id, t.name)} className="nx-btn-ghost p-1.5 text-red-400">
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

export default RatesIndex;
