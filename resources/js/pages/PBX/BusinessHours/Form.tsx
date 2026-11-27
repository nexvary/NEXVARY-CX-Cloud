import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const TIMEZONES = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','Europe/London','Europe/Paris','Asia/Riyadh','Asia/Dubai'];

interface Slot { day: string; open_time: string; close_time: string }
interface Schedule {
  id?: string;
  name: string;
  schedule: Slot[];
  holidays: string[];
  timezone: string;
  enabled: boolean;
}

export default function BusinessHoursForm({ schedule }: { schedule: Schedule | null }) {
  const { data, setData, post, patch, processing, errors } = useForm({
    name:     schedule?.name     ?? '',
    schedule: schedule?.schedule ?? [] as Slot[],
    holidays: schedule?.holidays ?? [] as string[],
    timezone: schedule?.timezone ?? 'UTC',
    enabled:  schedule?.enabled  ?? true,
  });

  const addSlot = () => setData('schedule', [...data.schedule, { day: '1', open_time: '09:00', close_time: '17:00' }]);
  const removeSlot = (i: number) => setData('schedule', data.schedule.filter((_, idx) => idx !== i));
  const updateSlot = (i: number, k: keyof Slot, v: string) =>
    setData('schedule', data.schedule.map((s, idx) => idx === i ? { ...s, [k]: v } : s));

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    schedule?.id ? patch(route('pbx.business-hours.update', { businessHour: schedule.id })) : post(route('pbx.business-hours.store'));
  };

  return (
    <AppLayout>
      <Head title={schedule ? 'Edit Schedule' : 'New Schedule'} />
      <div className="p-6 max-w-2xl mx-auto">
        <h1 className="text-xl font-semibold text-white mb-6">{schedule ? 'Edit' : 'New'} Business Hours Schedule</h1>
        <form onSubmit={submit} className="space-y-5">
          <div>
            <label className="nx-label">Name</label>
            <input className={`nx-input ${errors.name ? 'error' : ''}`} value={data.name}
              onChange={e => setData('name', e.target.value)}/>
            {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
          </div>

          <div>
            <label className="nx-label">Timezone</label>
            <select className="nx-input" value={data.timezone} onChange={e => setData('timezone', e.target.value)}>
              {TIMEZONES.map(tz => <option key={tz} value={tz}>{tz}</option>)}
            </select>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="nx-label mb-0">Schedule Slots</label>
              <button type="button" onClick={addSlot} className="text-blue-400 text-xs hover:text-blue-300">+ Add Slot</button>
            </div>
            <div className="space-y-2">
              {data.schedule.map((slot, i) => (
                <div key={i} className="flex items-center gap-2">
                  <select className="nx-input flex-1" value={slot.day} onChange={e => updateSlot(i, 'day', e.target.value)}>
                    {DAYS.map((d, idx) => <option key={d} value={String(idx+1)}>{d}</option>)}
                    <option value="2-6">Mon–Fri</option>
                    <option value="1-7">Every day</option>
                  </select>
                  <input type="time" className="nx-input w-32" value={slot.open_time} onChange={e => updateSlot(i, 'open_time', e.target.value)}/>
                  <span className="text-gray-500">–</span>
                  <input type="time" className="nx-input w-32" value={slot.close_time} onChange={e => updateSlot(i, 'close_time', e.target.value)}/>
                  <button type="button" onClick={() => removeSlot(i)} className="text-red-400 text-xs">✕</button>
                </div>
              ))}
              {data.schedule.length === 0 && <p className="text-gray-500 text-xs">No slots — add at least one.</p>}
            </div>
          </div>

          <label className="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" checked={data.enabled} onChange={e => setData('enabled', e.target.checked)} className="rounded"/>
            <span className="text-gray-300 text-sm">Enabled</span>
          </label>

          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={processing} className="nx-btn-primary px-6 py-2 text-sm">
              {processing ? 'Saving…' : 'Save'}
            </button>
            <a href={route('pbx.business-hours.index')} className="nx-btn-secondary px-6 py-2 text-sm">Cancel</a>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
