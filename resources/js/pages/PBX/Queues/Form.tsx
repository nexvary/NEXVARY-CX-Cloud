import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface CallQueue { id?: string; name: string; extension: string; strategy: string; timeout: number; enabled: boolean }
interface Props { queue?: CallQueue }

const QueueForm: React.FC<Props> = ({ queue }) => {
  const isEdit = Boolean(queue?.id);
  const { data, setData, post, patch, processing, errors } = useForm({
    name: queue?.name ?? '',
    extension: queue?.extension ?? '',
    strategy: queue?.strategy ?? 'ring-all',
    timeout: queue?.timeout ?? 60,
    enabled: queue?.enabled ?? true,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) patch(`/pbx/queues/${queue!.id}`);
    else post('/pbx/queues');
  };

  return (
    <AppLayout title={isEdit ? 'Edit Queue' : 'New Queue'}>
      <Head title={isEdit ? 'Edit Queue' : 'New Queue'} />
      <div className="p-6 max-w-2xl space-y-6">
        <div className="flex items-center gap-3">
          <Link href="/pbx/queues" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>
            {isEdit ? 'Edit Call Queue' : 'New Call Queue'}
          </h1>
        </div>

        <form onSubmit={handleSubmit} className="nx-card p-6 space-y-5">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="nx-label">Queue Name</label>
              <input className="nx-input" value={data.name} onChange={e => setData('name', e.target.value)} required />
              {errors.name && <p className="nx-error">{errors.name}</p>}
            </div>
            <div>
              <label className="nx-label">Extension</label>
              <input className="nx-input font-mono" value={data.extension} onChange={e => setData('extension', e.target.value)} required />
              {errors.extension && <p className="nx-error">{errors.extension}</p>}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="nx-label">Ring Strategy</label>
              <select className="nx-select" value={data.strategy} onChange={e => setData('strategy', e.target.value)}>
                <option value="ring-all">Ring All</option>
                <option value="round-robin">Round Robin</option>
                <option value="least-calls">Least Calls</option>
                <option value="random">Random</option>
              </select>
            </div>
            <div>
              <label className="nx-label">Timeout (seconds)</label>
              <input type="number" className="nx-input" value={data.timeout}
                     onChange={e => setData('timeout', parseInt(e.target.value))} min={30} max={3600} />
            </div>
          </div>

          <div className="flex items-center gap-2">
            <input type="checkbox" id="enabled" checked={data.enabled}
                   onChange={e => setData('enabled', e.target.checked)} className="rounded" />
            <label htmlFor="enabled" className="text-sm" style={{ color: 'var(--nx-silver)' }}>Enabled</label>
          </div>

          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={processing} className="nx-btn-primary gap-2">
              <Save className="w-4 h-4" />{isEdit ? 'Update' : 'Create'} Queue
            </button>
            <Link href="/pbx/queues" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};

export default QueueForm;
