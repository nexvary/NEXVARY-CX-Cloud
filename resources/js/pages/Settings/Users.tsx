import React from 'react';
import { Head } from '@inertiajs/react';
import { Users, Plus } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const UsersSettings: React.FC = () => (
  <AppLayout title="Users">
    <Head title="Users" />
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>Users</h1>
          <p className="text-sm text-nx-muted mt-0.5">Manage user accounts and permissions</p>
        </div>
        <button className="nx-btn-primary gap-2">
          <Plus className="w-4 h-4" /> New User
        </button>
      </div>
      <div className="nx-card p-8 text-center text-nx-muted">
        <Users className="w-10 h-10 mx-auto mb-3 opacity-30" />
        <p className="font-medium">User Management</p>
        <p className="text-sm mt-1">Full user management interface coming in Milestone 4.</p>
      </div>
    </div>
  </AppLayout>
);

export default UsersSettings;
