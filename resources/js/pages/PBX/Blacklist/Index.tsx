import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { useTranslation } from 'react-i18next';

interface Entry {
  id: string;
  pattern: string;
  pattern_type: 'exact' | 'prefix' | 'regex';
  direction: 'inbound' | 'outbound' | 'both';
  description: string | null;
  enabled: boolean;
}

interface Props { entries: Entry[] }

export default function BlacklistIndex({ entries }: Props) {
  const { t } = useTranslation();

  const remove = (id: string) => {
    if (!confirm(t('common.confirm_delete'))) return;
    router.delete(route('pbx.blacklist.destroy', id), { preserveScroll: true });
  };

  const dirBadge = (d: string) =>
    d === 'inbound' ? 'bg-blue-500/20 text-blue-300' :
    d === 'outbound' ? 'bg-orange-500/20 text-orange-300' : 'bg-purple-500/20 text-purple-300';

  return (
    <AppLayout>
      <Head title={t('nav.blacklist')} />
      <div className="p-6 max-w-5xl mx-auto space-y-4">
        <div className="flex items-center justify-between">
          <h1 className="text-xl font-semibold text-white">{t('nav.blacklist')}</h1>
          <Link href={route('pbx.blacklist.create')} className="nx-btn-primary text-sm px-4 py-2">
            + {t('common.add')}
          </Link>
        </div>

        {entries.length === 0 ? (
          <p className="text-gray-400 text-sm">{t('common.no_records')}</p>
        ) : (
          <div className="nx-card overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-white/5 text-gray-400 text-xs uppercase">
                <tr>
                  <th className="px-4 py-3 text-left">Pattern</th>
                  <th className="px-4 py-3 text-left">Type</th>
                  <th className="px-4 py-3 text-left">Direction</th>
                  <th className="px-4 py-3 text-left">Description</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3"/>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {entries.map(e => (
                  <tr key={e.id} className="hover:bg-white/5">
                    <td className="px-4 py-3 font-mono text-white">{e.pattern}</td>
                    <td className="px-4 py-3 text-gray-300">{e.pattern_type}</td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${dirBadge(e.direction)}`}>
                        {e.direction}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-400">{e.description ?? '—'}</td>
                    <td className="px-4 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded ${e.enabled ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}`}>
                        {e.enabled ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right space-x-3">
                      <Link href={route('pbx.blacklist.edit', e.id)} className="text-blue-400 hover:text-blue-300 text-xs">Edit</Link>
                      <button onClick={() => remove(e.id)} className="text-red-400 hover:text-red-300 text-xs">Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
