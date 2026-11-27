import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, CreditCard, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface BillingAccount {
  id: string;
  name: string;
  currency: string;
  credit: number;
  enabled: boolean;
}

interface Props {
  accounts: { data: BillingAccount[]; total: number };
}

const AccountsIndex: React.FC<Props> = ({ accounts }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete account "${name}"?`)) return;
    router.delete(`/billing/accounts/${id}`);
  };

  return (
    <AppLayout title="Billing Accounts">
      <Head title="Billing Accounts" />
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Billing Accounts</h1>
          <p className="nx-page-subtitle">Manage customer billing accounts and credit</p>
        </div>
        <Link href="/billing/accounts/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New Account
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Account Name</th>
                <th>Currency</th>
                <th>Credit Balance</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {accounts.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-nx-muted">
                    <CreditCard className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No billing accounts yet.
                  </td>
                </tr>
              )}
              {accounts.data.map(a => (
                <tr key={a.id}>
                  <td className="font-medium text-nx-primary">{a.name}</td>
                  <td className="text-nx-secondary font-mono">{a.currency}</td>
                  <td className="font-mono text-nx-primary">{a.credit?.toFixed(2) ?? '0.00'}</td>
                  <td>
                    <span className={`nx-badge ${a.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      {a.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/billing/accounts/${a.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(a.id, a.name)} className="nx-btn-ghost p-1.5 text-red-400">
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

export default AccountsIndex;
