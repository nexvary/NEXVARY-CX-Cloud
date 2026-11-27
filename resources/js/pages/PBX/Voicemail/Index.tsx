import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Voicemail, Inbox } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Mailbox {
  id: string; extension: string; description?: string;
  enabled: boolean; message_count: number; unread_count: number;
}
interface Props { mailboxes: Mailbox[] }

const VoicemailIndex: React.FC<Props> = ({ mailboxes }) => (
  <AppLayout title="Voicemail">
    <Head title="Voicemail" />
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>Voicemail</h1>
        <p className="text-sm text-nx-muted mt-0.5">{mailboxes.length} voicemail boxes</p>
      </div>

      <div className="nx-card overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-nx text-nx-muted text-xs uppercase tracking-wider">
              <th className="text-start px-4 py-3">Extension</th>
              <th className="text-start px-4 py-3">Description</th>
              <th className="text-start px-4 py-3">Messages</th>
              <th className="text-start px-4 py-3">Unread</th>
              <th className="text-start px-4 py-3">Status</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-nx">
            {mailboxes.map(box => (
              <tr key={box.id} className="hover:bg-white/[0.02] transition-colors">
                <td className="px-4 py-3 font-mono text-nx-primary">{box.extension}</td>
                <td className="px-4 py-3 text-nx-muted">{box.description || '—'}</td>
                <td className="px-4 py-3 text-nx-muted">{box.message_count}</td>
                <td className="px-4 py-3">
                  {box.unread_count > 0 ? (
                    <span className="nx-badge nx-badge-blue">{box.unread_count} new</span>
                  ) : <span className="text-nx-muted">0</span>}
                </td>
                <td className="px-4 py-3">
                  <span className={`nx-badge ${box.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                    {box.enabled ? 'Active' : 'Disabled'}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <Link href={`/pbx/voicemail/${box.id}`} className="nx-btn-ghost p-1.5">
                    <Inbox className="w-4 h-4" />
                  </Link>
                </td>
              </tr>
            ))}
            {mailboxes.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-nx-muted">
                  <Voicemail className="w-8 h-8 mx-auto mb-2 opacity-30" />
                  No voicemail boxes. Enable voicemail on extensions to see them here.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
);

export default VoicemailIndex;
