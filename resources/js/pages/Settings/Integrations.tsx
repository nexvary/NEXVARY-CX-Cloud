import React from 'react';
import { Head } from '@inertiajs/react';
import { Package, CheckCircle, XCircle, ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Props { telnyx_configured: boolean }

const IntegrationsSettings: React.FC<Props> = ({ telnyx_configured }) => (
  <AppLayout title="Integrations">
    <Head title="Integrations" />
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>Integrations</h1>
        <p className="text-sm text-nx-muted mt-0.5">Connect external service providers</p>
      </div>

      <div className="nx-card p-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-500/10">
              <Package className="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <p className="font-medium" style={{ color: 'var(--nx-silver)' }}>Telnyx</p>
              <p className="text-sm text-nx-muted">Cloud communications — SMS, voice trunks</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            {telnyx_configured ? (
              <span className="flex items-center gap-1.5 text-sm text-green-400">
                <CheckCircle className="w-4 h-4" /> Configured
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-sm text-nx-muted">
                <XCircle className="w-4 h-4" /> Not configured
              </span>
            )}
            <a href="https://portal.telnyx.com" target="_blank" rel="noopener noreferrer"
               className="nx-btn-primary gap-1 text-sm">
              <ExternalLink className="w-3.5 h-3.5" /> Open Telnyx Portal
            </a>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
);

export default IntegrationsSettings;
