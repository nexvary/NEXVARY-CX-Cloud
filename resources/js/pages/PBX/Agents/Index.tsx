import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Agent {
  id: string;
  name: string;
  status: 'available' | 'unavailable' | 'on_break';
  skill_level: number;
  enabled: boolean;
  extension?: { number: string; description: string | null };
}

interface Props { agents: { data: Agent[]; current_page: number; last_page: number } }

const statusColor = (s: string) =>
  s === 'available' ? 'bg-green-500/20 text-green-400' :
  s === 'on_break' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-gray-500/20 text-gray-400';

export default function AgentsIndex({ agents }: Props) {
  const del = (id: string) => {
    if (!confirm('Delete agent?')) return;
    router.delete(route('pbx.agents.destroy', { agent: id }), { preserveScroll: true });
  };

  const setStatus = (id: string, status: string) =>
    router.post(route('pbx.agents.set-status', { agent: id }), { status }, { preserveScroll: true });

  return (
    <AppLayout>
      <Head title="Call Agents" />
      <div className="p-6 max-w-5xl mx-auto space-y-4">
        <div className="flex items-center justify-between">
          <h1 className="text-xl font-semibold text-white">Call Agents</h1>
          <Link href={route('pbx.agents.create')} className="nx-btn-primary text-sm px-4 py-2">
            + Add Agent
          </Link>
        </div>

        {agents.data.length === 0 ? (
          <p className="text-gray-400 text-sm">No agents configured.</p>
        ) : (
          <div className="nx-card overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-white/5 text-gray-400 text-xs uppercase">
                <tr>
                  <th className="px-4 py-3 text-left">Name</th>
                  <th className="px-4 py-3 text-left">Extension</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Skill</th>
                  <th className="px-4 py-3"/>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {agents.data.map(a => (
                  <tr key={a.id} className="hover:bg-white/5">
                    <td className="px-4 py-3 text-white">{a.name}</td>
                    <td className="px-4 py-3 text-gray-300 font-mono">
                      {a.extension ? `${a.extension.number}${a.extension.description ? ` — ${a.extension.description}` : ''}` : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <select
                        value={a.status}
                        onChange={e => setStatus(a.id, e.target.value)}
                        className={`text-xs px-2 py-1 rounded font-medium border-0 cursor-pointer ${statusColor(a.status)}`}
                        style={{background: 'transparent'}}
                      >
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                        <option value="on_break">On Break</option>
                      </select>
                    </td>
                    <td className="px-4 py-3 text-gray-300">{a.skill_level}</td>
                    <td className="px-4 py-3 text-right space-x-3">
                      <Link href={route('pbx.agents.edit', { agent: a.id })} className="text-blue-400 text-xs">Edit</Link>
                      <button onClick={() => del(a.id)} className="text-red-400 text-xs">Delete</button>
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
