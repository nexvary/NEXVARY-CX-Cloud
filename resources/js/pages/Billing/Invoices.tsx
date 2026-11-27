import React from 'react';
import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const Invoices: React.FC = () => (
  <AppLayout title="Invoices">
    <Head title="Invoices" />
    <div className="nx-page-header">
      <h1 className="nx-page-title">Invoices</h1>
      <p className="nx-page-subtitle">Billing history and downloadable invoices</p>
    </div>
    <div className="p-6 nx-animate-in">
      <div className="nx-card p-16 text-center">
        <FileText className="w-12 h-12 mx-auto mb-4 opacity-20" />
        <p className="text-nx-muted text-sm">No invoices generated yet.</p>
        <p className="text-xs text-nx-muted mt-2">Invoices are generated automatically at the end of each billing cycle.</p>
      </div>
    </div>
  </AppLayout>
);

export default Invoices;
