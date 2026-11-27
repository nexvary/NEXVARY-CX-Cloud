import React from 'react';
import { Head } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const BillingReports: React.FC = () => (
  <AppLayout title="Billing Reports">
    <Head title="Billing Reports" />
    <div className="nx-page-header">
      <h1 className="nx-page-title">Billing Reports</h1>
      <p className="nx-page-subtitle">Usage analytics and cost breakdowns</p>
    </div>
    <div className="p-6 nx-animate-in">
      <div className="nx-card p-16 text-center">
        <BarChart3 className="w-12 h-12 mx-auto mb-4 opacity-20" />
        <p className="text-nx-muted text-sm">Billing report generation coming soon.</p>
        <p className="text-xs text-nx-muted mt-2">Reports will show per-account usage, per-destination costs, and trend analysis.</p>
      </div>
    </div>
  </AppLayout>
);

export default BillingReports;
