import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Entry {
  id?: string;
  pattern: string;
  pattern_type: 'exact' | 'prefix' | 'regex';
  direction: 'inbound' | 'outbound' | 'both';
  description: string;
  enabled: boolean;
}

export default function BlacklistForm({ entry }: { entry: Entry | null }) {
  const { data, setData, post, patch, processing, errors } = useForm({
    pattern:      entry?.pattern      ?? '',
    pattern_type: entry?.pattern_type ?? 'exact',
    direction:    entry?.direction    ?? 'inbound',
    description:  entry?.description  ?? '',
    enabled:      entry?.enabled      ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (entry?.id) {
      patch(route('pbx.blacklist.update', { entry: entry.id }));
    } else {
      post(route('pbx.blacklist.store'));
    }
  };

  return (
    <AppLayout>
      <Head title={entry ? 'Edit Blacklist Entry' : 'Add Blacklist Entry'} />
      <div className="p-6 max-w-lg mx-auto">
        <h1 className="text-xl font-semibold text-white mb-6">{entry ? 'Edit' : 'Add'} Blacklist Entry</h1>
        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="nx-label">Pattern</label>
            <input className={`nx-input ${errors.pattern ? 'error' : ''}`} value={data.pattern}
              onChange={e => setData('pattern', e.target.value)} placeholder="+15550001234 or ^123 or regex"/>
            {errors.pattern && <p className="text-red-400 text-xs mt-1">{errors.pattern}</p>}
          </div>

          <div>
            <label className="nx-label">Pattern Type</label>
            <select className="nx-input" value={data.pattern_type} onChange={e => setData('pattern_type', e.target.value as 'exact' | 'prefix' | 'regex')}>
              <option value="exact">Exact match</option>
              <option value="prefix">Prefix match</option>
              <option value="regex">Regular expression</option>
            </select>
          </div>

          <div>
            <label className="nx-label">Direction</label>
            <select className="nx-input" value={data.direction} onChange={e => setData('direction', e.target.value as 'inbound' | 'outbound' | 'both')}>
              <option value="inbound">Inbound (block incoming)</option>
              <option value="outbound">Outbound (block dialing)</option>
              <option value="both">Both directions</option>
            </select>
          </div>

          <div>
            <label className="nx-label">Description (optional)</label>
            <input className="nx-input" value={data.description}
              onChange={e => setData('description', e.target.value)} placeholder="Why this number is blocked"/>
          </div>

          <label className="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" checked={data.enabled} onChange={e => setData('enabled', e.target.checked)} className="rounded"/>
            <span className="text-gray-300 text-sm">Enabled</span>
          </label>

          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={processing} className="nx-btn-primary px-6 py-2 text-sm">
              {processing ? 'Saving…' : 'Save'}
            </button>
            <a href={route('pbx.blacklist.index')} className="nx-btn-secondary px-6 py-2 text-sm">Cancel</a>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
