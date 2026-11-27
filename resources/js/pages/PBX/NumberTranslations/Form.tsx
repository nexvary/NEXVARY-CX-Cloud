import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, ArrowLeftRight } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface NumberTranslation {
  id?: string; name?: string; direction?: string; match_pattern?: string;
  strip_digits?: number; add_prefix?: string; caller_id_override?: string;
  privacy_enabled?: boolean; enabled?: boolean; priority?: number;
}
interface Props { translation?: NumberTranslation; }

const NumberTranslationForm: React.FC<Props> = ({ translation }) => {
  const isEdit = Boolean(translation?.id);
  const { data, setData, post, patch, processing, errors } = useForm({
    name:               translation?.name ?? '',
    direction:          translation?.direction ?? 'both',
    match_pattern:      translation?.match_pattern ?? '',
    strip_digits:       translation?.strip_digits ?? 0,
    add_prefix:         translation?.add_prefix ?? '',
    caller_id_override: translation?.caller_id_override ?? '',
    privacy_enabled:    translation?.privacy_enabled ?? false,
    priority:           translation?.priority ?? 100,
    enabled:            translation?.enabled ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) patch(`/pbx/number-translations/${translation!.id}`);
    else post('/pbx/number-translations');
  };

  return (
    <AppLayout title={isEdit ? 'Edit Translation Rule' : 'New Translation Rule'}>
      <Head title={isEdit ? 'Edit Translation Rule' : 'New Translation Rule'} />
      <div className="nx-page-header flex items-center gap-3">
        <Link href="/pbx/number-translations" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
        <div>
          <h1 className="nx-page-title">{isEdit ? `Edit: ${translation?.name}` : 'New Translation Rule'}</h1>
          <p className="nx-page-subtitle">Caller ID and number normalization</p>
        </div>
      </div>
      <div className="p-6 max-w-2xl nx-animate-in">
        <form onSubmit={submit} className="space-y-6">
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <ArrowLeftRight className="w-4 h-4" style={{ color: 'var(--nx-green)' }} /> Rule Settings
            </h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Rule Name *</label>
                <input className={`nx-input ${errors.name ? 'error' : ''}`} value={data.name}
                  onChange={e => setData('name', e.target.value)} placeholder="e.g. strip-country-code" />
                {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
              </div>
              <div>
                <label className="nx-label">Direction</label>
                <select className="nx-input" value={data.direction} onChange={e => setData('direction', e.target.value as any)}>
                  <option value="both">Both</option>
                  <option value="inbound">Inbound</option>
                  <option value="outbound">Outbound</option>
                </select>
              </div>
              <div className="col-span-2">
                <label className="nx-label">Match Pattern (regex) *</label>
                <input className={`nx-input font-mono ${errors.match_pattern ? 'error' : ''}`}
                  value={data.match_pattern} onChange={e => setData('match_pattern', e.target.value)}
                  placeholder="^(\d{10})$" />
                {errors.match_pattern && <p className="text-xs text-red-400 mt-1">{errors.match_pattern}</p>}
              </div>
              <div>
                <label className="nx-label">Strip Digits</label>
                <input type="number" className="nx-input" value={data.strip_digits as number} min={0}
                  onChange={e => setData('strip_digits', parseInt(e.target.value) || 0)} />
              </div>
              <div>
                <label className="nx-label">Add Prefix</label>
                <input className="nx-input font-mono" value={data.add_prefix}
                  onChange={e => setData('add_prefix', e.target.value)} placeholder="e.g. +1" />
              </div>
              <div>
                <label className="nx-label">Caller ID Override</label>
                <input className="nx-input font-mono" value={data.caller_id_override}
                  onChange={e => setData('caller_id_override', e.target.value)} placeholder="optional" />
              </div>
              <div>
                <label className="nx-label">Priority</label>
                <input type="number" className="nx-input" value={data.priority as number}
                  onChange={e => setData('priority', parseInt(e.target.value))} />
              </div>
              <div className="flex items-center gap-6 col-span-2">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" className="nx-checkbox" checked={data.privacy_enabled as boolean}
                    onChange={e => setData('privacy_enabled', e.target.checked)} />
                  <span className="nx-label mb-0">Enable Privacy</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" className="nx-checkbox" checked={data.enabled as boolean}
                    onChange={e => setData('enabled', e.target.checked)} />
                  <span className="nx-label mb-0">Enable Rule</span>
                </label>
              </div>
            </div>
          </div>
          <div className="flex gap-3">
            <button type="submit" disabled={processing} className="nx-btn-primary">
              <Save className="w-4 h-4" /> {processing ? 'Saving...' : 'Save Rule'}
            </button>
            <Link href="/pbx/number-translations" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};
export default NumberTranslationForm;
