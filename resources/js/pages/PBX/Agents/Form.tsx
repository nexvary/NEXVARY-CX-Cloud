import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Extension { id: string; number: string; description: string | null }
interface Agent {
  id?: string;
  name: string;
  extension_id: string | null;
  status: 'available' | 'unavailable' | 'on_break';
  skill_level: number;
  enabled: boolean;
}

export default function AgentForm({ agent, extensions }: { agent: Agent | null; extensions: Extension[] }) {
  const { data, setData, post, patch, processing, errors } = useForm({
    name:         agent?.name         ?? '',
    extension_id: agent?.extension_id ?? '',
    status:       agent?.status       ?? 'available',
    skill_level:  agent?.skill_level  ?? 1,
    enabled:      agent?.enabled      ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (agent?.id) {
      patch(route('pbx.agents.update', { agent: agent.id }));
    } else {
      post(route('pbx.agents.store'));
    }
  };

  return (
    <AppLayout>
      <Head title={agent ? 'Edit Agent' : 'New Agent'} />
      <div className="p-6 max-w-lg mx-auto">
        <h1 className="text-xl font-semibold text-white mb-6">{agent ? 'Edit' : 'New'} Call Agent</h1>
        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="nx-label">Name</label>
            <input className={`nx-input ${errors.name ? 'error' : ''}`} value={data.name}
              onChange={e => setData('name', e.target.value)}/>
            {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
          </div>

          <div>
            <label className="nx-label">Extension</label>
            <select className="nx-input" value={data.extension_id ?? ''}
              onChange={e => setData('extension_id', e.target.value)}>
              <option value="">— None —</option>
              {extensions.map(ext => (
                <option key={ext.id} value={ext.id}>{ext.number}{ext.description ? ` — ${ext.description}` : ''}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="nx-label">Initial Status</label>
            <select className="nx-input" value={data.status} onChange={e => setData('status', e.target.value as any)}>
              <option value="available">Available</option>
              <option value="unavailable">Unavailable</option>
              <option value="on_break">On Break</option>
            </select>
          </div>

          <div>
            <label className="nx-label">Skill Level (1–10)</label>
            <input type="number" min="1" max="10" className="nx-input w-24" value={data.skill_level}
              onChange={e => setData('skill_level', parseInt(e.target.value))}/>
          </div>

          <label className="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" checked={data.enabled} onChange={e => setData('enabled', e.target.checked)} className="rounded"/>
            <span className="text-gray-300 text-sm">Enabled</span>
          </label>

          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={processing} className="nx-btn-primary px-6 py-2 text-sm">
              {processing ? 'Saving…' : 'Save'}
            </button>
            <a href={route('pbx.agents.index')} className="nx-btn-secondary px-6 py-2 text-sm">Cancel</a>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
