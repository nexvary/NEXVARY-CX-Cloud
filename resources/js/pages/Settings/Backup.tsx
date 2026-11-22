import React from 'react';
import { Head } from '@inertiajs/react';
import { HardDrive, Plus, Download } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const BackupSettings: React.FC = () => (
  <AppLayout title="Backup & Restore">
    <Head title="Backup & Restore" />
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>Backup & Restore</h1>
          <p className="text-sm text-nx-muted mt-0.5">Manage system backups and disaster recovery</p>
        </div>
        <button className="nx-btn-primary gap-2">
          <Plus className="w-4 h-4" /> Create Backup
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {['Database', 'PBX Config', 'Recordings'].map(item => (
          <div key={item} className="nx-card p-4 flex items-center gap-3">
            <HardDrive className="w-5 h-5 text-nx-primary shrink-0" />
            <div>
              <p className="text-sm font-medium" style={{ color: 'var(--nx-silver)' }}>{item}</p>
              <p className="text-xs text-nx-muted">No backup yet</p>
            </div>
          </div>
        ))}
      </div>

      <div className="nx-card p-8 text-center text-nx-muted">
        <Download className="w-10 h-10 mx-auto mb-3 opacity-30" />
        <p className="font-medium">Backup Management</p>
        <p className="text-sm mt-1">Automated backup scheduling and restore capabilities coming in Milestone 7.</p>
      </div>
    </div>
  </AppLayout>
);

export default BackupSettings;
