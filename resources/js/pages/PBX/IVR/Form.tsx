import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface IvrMenu { id?: string; name: string; greeting_tts?: string; timeout_ms: number; max_retries: number; enabled: boolean }
interface Props { menu?: IvrMenu }

const IvrForm: React.FC<Props> = ({ menu }) => {
  const isEdit = Boolean(menu?.id);
  const { data, setData, post, patch, processing, errors } = useForm({
    name: menu?.name ?? '',
    greeting_tts: menu?.greeting_tts ?? '',
    timeout_ms: menu?.timeout_ms ?? 10000,
    max_retries: menu?.max_retries ?? 3,
    enabled: menu?.enabled ?? true,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) patch(`/pbx/ivr/${menu!.id}`);
    else post('/pbx/ivr');
  };

  return (
    <AppLayout title={isEdit ? 'Edit IVR Menu' : 'New IVR Menu'}>
      <Head title={isEdit ? 'Edit IVR Menu' : 'New IVR Menu'} />
      <div className="p-6 max-w-2xl space-y-6">
        <div className="flex items-center gap-3">
          <Link href="/pbx/ivr" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>
            {isEdit ? 'Edit IVR Menu' : 'New IVR Menu'}
          </h1>
        </div>

        <form onSubmit={handleSubmit} className="nx-card p-6 space-y-5">
          <div>
            <label className="nx-label">Menu Name</label>
            <input className="nx-input" value={data.name} onChange={e => setData('name', e.target.value)} required />
            {errors.name && <p className="nx-error">{errors.name}</p>}
          </div>

          <div>
            <label className="nx-label">Greeting (Text-to-Speech)</label>
            <textarea className="nx-input min-h-[80px]" value={data.greeting_tts}
                      onChange={e => setData('greeting_tts', e.target.value)}
                      placeholder="Press 1 for Sales, Press 2 for Support..." />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="nx-label">Timeout (ms)</label>
              <input type="number" className="nx-input" value={data.timeout_ms}
                     onChange={e => setData('timeout_ms', parseInt(e.target.value))} min={1000} max={60000} />
            </div>
            <div>
              <label className="nx-label">Max Retries</label>
              <input type="number" className="nx-input" value={data.max_retries}
                     onChange={e => setData('max_retries', parseInt(e.target.value))} min={1} max={10} />
            </div>
          </div>

          <div className="flex items-center gap-2">
            <input type="checkbox" id="enabled" checked={data.enabled}
                   onChange={e => setData('enabled', e.target.checked)} className="rounded" />
            <label htmlFor="enabled" className="text-sm" style={{ color: 'var(--nx-silver)' }}>Enabled</label>
          </div>

          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={processing} className="nx-btn-primary gap-2">
              <Save className="w-4 h-4" />{isEdit ? 'Update' : 'Create'} IVR Menu
            </button>
            <Link href="/pbx/ivr" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};

export default IvrForm;
