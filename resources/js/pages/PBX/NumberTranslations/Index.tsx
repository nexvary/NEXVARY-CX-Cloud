import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, ArrowLeftRight, Edit, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface NumberTranslation {
  id: string; name: string; direction: string; match_pattern: string;
  strip_digits: number; add_prefix: string | null; priority: number;
  privacy_enabled: boolean; enabled: boolean;
}
interface Props { translations: NumberTranslation[]; }

const NumberTranslationsIndex: React.FC<Props> = ({ translations }) => {
  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete rule "${name}"?`)) return;
    router.delete(`/pbx/number-translations/${id}`);
  };

  return (
    <AppLayout title="Number Translations">
      <Head title="Number Translations" />
      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Number Translations</h1>
          <p className="nx-page-subtitle">Caller ID, privacy and number normalization</p>
        </div>
        <Link href="/pbx/number-translations/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" /> New Rule
        </Link>
      </div>
      <div className="p-6 nx-animate-in">
        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Priority</th><th>Name</th><th>Direction</th><th>Match</th>
                <th>Transform</th><th>Flags</th><th>Status</th><th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {translations.length === 0 && (
                <tr>
                  <td colSpan={8} className="text-center py-12 text-nx-muted">
                    <ArrowLeftRight className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    <p>No translation rules configured.</p>
                    <Link href="/pbx/number-translations/create" className="nx-btn-primary mt-4 inline-flex">
                      <Plus className="w-4 h-4" /> Create first rule
                    </Link>
                  </td>
                </tr>
              )}
              {translations.map(t => (
                <tr key={t.id}>
                  <td className="font-mono text-sm text-center">{t.priority}</td>
                  <td className="font-medium text-nx-primary">{t.name}</td>
                  <td><span className="nx-badge nx-badge-blue capitalize">{t.direction}</span></td>
                  <td className="font-mono text-xs text-nx-muted">{t.match_pattern}</td>
                  <td className="text-sm text-nx-muted">
                    {t.strip_digits > 0 && <span>-{t.strip_digits}</span>}
                    {t.add_prefix && <span className="ms-1 text-green-400">+{t.add_prefix}</span>}
                    {!t.strip_digits && !t.add_prefix && '—'}
                  </td>
                  <td>{t.privacy_enabled && <span className="nx-badge nx-badge-yellow">Privacy</span>}</td>
                  <td>
                    <span className={`nx-badge ${t.enabled ? 'nx-badge-green' : 'nx-badge-red'}`}>
                      <span className={`nx-dot me-1.5 ${t.enabled ? 'nx-dot-green' : 'nx-dot-red'}`} />
                      {t.enabled ? 'Active' : 'Disabled'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/pbx/number-translations/${t.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button onClick={() => handleDelete(t.id, t.name)} className="nx-btn-ghost p-1.5 text-red-400 hover:text-red-300">
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
export default NumberTranslationsIndex;
