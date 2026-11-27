import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface MohClass {
  id: string;
  name: string;
  source: 'files' | 'stream_url';
  stream_url: string | null;
  shuffle: boolean;
  enabled: boolean;
}

export default function MohIndex({ classes }: { classes: MohClass[] }) {
  const [adding, setAdding] = useState(false);
  const { data, setData, post, processing, reset, errors } = useForm({
    name: '', source: 'files' as 'files' | 'stream_url', stream_url: '', shuffle: false, enabled: true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('pbx.moh.store'), { onSuccess: () => { setAdding(false); reset(); } });
  };

  const del = (id: string) => { if (confirm('Delete MoH class?')) router.delete(route('pbx.moh.destroy', { moh: id }), { preserveScroll: true }); };

  return (
    <AppLayout>
      <Head title="Music on Hold" />
      <div className="p-6 max-w-4xl mx-auto space-y-4">
        <div className="flex items-center justify-between">
          <h1 className="text-xl font-semibold text-white">Music on Hold</h1>
          <button onClick={() => setAdding(!adding)} className="nx-btn-primary text-sm px-4 py-2">
            {adding ? 'Cancel' : '+ New Class'}
          </button>
        </div>

        {adding && (
          <form onSubmit={submit} className="nx-card p-4 space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="nx-label">Name</label>
                <input className="nx-input" value={data.name} onChange={e => setData('name', e.target.value)} required/>
              </div>
              <div>
                <label className="nx-label">Source</label>
                <select className="nx-input" value={data.source} onChange={e => setData('source', e.target.value as any)}>
                  <option value="files">Local files</option>
                  <option value="stream_url">Stream URL</option>
                </select>
              </div>
            </div>
            {data.source === 'stream_url' && (
              <div>
                <label className="nx-label">Stream URL</label>
                <input className="nx-input" value={data.stream_url} onChange={e => setData('stream_url', e.target.value)} placeholder="example.com/stream.mp3"/>
              </div>
            )}
            <div className="flex items-center gap-4">
              <label className="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                <input type="checkbox" checked={data.shuffle} onChange={e => setData('shuffle', e.target.checked)} className="rounded"/>
                Shuffle files
              </label>
              <label className="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                <input type="checkbox" checked={data.enabled} onChange={e => setData('enabled', e.target.checked)} className="rounded"/>
                Enabled
              </label>
            </div>
            <button type="submit" disabled={processing} className="nx-btn-primary text-sm px-5 py-1.5">
              {processing ? 'Saving…' : 'Create'}
            </button>
          </form>
        )}

        {classes.length === 0 ? (
          <p className="text-gray-400 text-sm">No MoH classes configured.</p>
        ) : (
          <div className="nx-card overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-white/5 text-gray-400 text-xs uppercase">
                <tr>
                  <th className="px-4 py-3 text-left">Name</th>
                  <th className="px-4 py-3 text-left">Source</th>
                  <th className="px-4 py-3 text-left">Shuffle</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3"/>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {classes.map(c => (
                  <tr key={c.id} className="hover:bg-white/5">
                    <td className="px-4 py-3 text-white">{c.name}</td>
                    <td className="px-4 py-3 text-gray-300">
                      {c.source === 'stream_url' ? <span className="font-mono text-xs">{c.stream_url}</span> : 'Local files'}
                    </td>
                    <td className="px-4 py-3 text-gray-400">{c.shuffle ? 'Yes' : 'No'}</td>
                    <td className="px-4 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded ${c.enabled ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}`}>
                        {c.enabled ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button onClick={() => del(c.id)} className="text-red-400 hover:text-red-300 text-xs">Delete</button>
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
