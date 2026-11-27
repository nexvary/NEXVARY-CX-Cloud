import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Schedule {
  id: string;
  name: string;
  timezone: string;
  enabled: boolean;
}

interface DayNight {
  id: string;
  name: string;
  mode: 'day' | 'night';
  day_destination: string | null;
  night_destination: string | null;
  enabled: boolean;
}

interface Props { schedules: Schedule[]; dayNight: DayNight[] }

export default function BusinessHoursIndex({ schedules, dayNight }: Props) {
  const del = (id: string) => { if (confirm('Delete schedule?')) router.delete(route('pbx.business-hours.destroy', { businessHour: id }), { preserveScroll: true }); };
  const toggle = (id: string) => router.post(route('pbx.business-hours.toggle-day-night', { dayNightMode: id }), {}, { preserveScroll: true });
  const delDnm = (id: string) => { if (confirm('Delete?')) router.delete(route('pbx.business-hours.destroy-day-night', { dayNightMode: id }), { preserveScroll: true }); };

  return (
    <AppLayout>
      <Head title="Business Hours & Day/Night Mode" />
      <div className="p-6 max-w-5xl mx-auto space-y-8">

        <section>
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-lg font-semibold text-white">Business Hours</h2>
            <Link href={route('pbx.business-hours.create')} className="nx-btn-primary text-sm px-4 py-2">+ Add Schedule</Link>
          </div>
          {schedules.length === 0 ? <p className="text-gray-400 text-sm">No schedules.</p> : (
            <div className="nx-card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-white/5 text-gray-400 text-xs uppercase">
                  <tr>
                    <th className="px-4 py-3 text-left">Name</th>
                    <th className="px-4 py-3 text-left">Timezone</th>
                    <th className="px-4 py-3 text-left">Status</th>
                    <th className="px-4 py-3"/>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {schedules.map(s => (
                    <tr key={s.id} className="hover:bg-white/5">
                      <td className="px-4 py-3 text-white">{s.name}</td>
                      <td className="px-4 py-3 text-gray-300">{s.timezone}</td>
                      <td className="px-4 py-3">
                        <span className={`text-xs px-2 py-0.5 rounded ${s.enabled ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}`}>
                          {s.enabled ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right space-x-3">
                        <Link href={route('pbx.business-hours.edit', { businessHour: s.id })} className="text-blue-400 text-xs">Edit</Link>
                        <button onClick={() => del(s.id)} className="text-red-400 text-xs">Delete</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <section>
          <h2 className="text-lg font-semibold text-white mb-3">Day / Night Mode</h2>
          {dayNight.length === 0 ? <p className="text-gray-400 text-sm">No day/night profiles.</p> : (
            <div className="nx-card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-white/5 text-gray-400 text-xs uppercase">
                  <tr>
                    <th className="px-4 py-3 text-left">Name</th>
                    <th className="px-4 py-3 text-left">Mode</th>
                    <th className="px-4 py-3 text-left">Day Dest</th>
                    <th className="px-4 py-3 text-left">Night Dest</th>
                    <th className="px-4 py-3"/>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {dayNight.map(d => (
                    <tr key={d.id} className="hover:bg-white/5">
                      <td className="px-4 py-3 text-white">{d.name}</td>
                      <td className="px-4 py-3">
                        <button onClick={() => toggle(d.id)}
                          className={`text-xs px-3 py-1 rounded font-medium ${d.mode === 'day' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-indigo-500/20 text-indigo-300'}`}>
                          {d.mode === 'day' ? '☀ Day' : '☽ Night'}
                        </button>
                      </td>
                      <td className="px-4 py-3 text-gray-300 font-mono">{d.day_destination ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-300 font-mono">{d.night_destination ?? '—'}</td>
                      <td className="px-4 py-3 text-right">
                        <button onClick={() => delDnm(d.id)} className="text-red-400 text-xs">Delete</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

      </div>
    </AppLayout>
  );
}
