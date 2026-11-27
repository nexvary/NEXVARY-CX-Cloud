import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, GitBranch, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Condition { field: string; expression: string; }
interface Action { application: string; data: string; anti_action?: boolean; }
interface DialplanRule {
  id?: string; name?: string; context?: string; priority?: number;
  continue?: boolean; enabled?: boolean;
  conditions?: Condition[]; actions?: Action[];
}
interface Props { rule?: DialplanRule; }

const DialplanRuleForm: React.FC<Props> = ({ rule }) => {
  const isEdit = Boolean(rule?.id);
  const [conditions, setConditions] = useState<Condition[]>(
    (rule?.conditions as Condition[] | undefined) ?? [{ field: 'destination_number', expression: '' }]
  );
  const [actions, setActions] = useState<Action[]>(
    (rule?.actions as Action[] | undefined) ?? [{ application: 'bridge', data: '' }]
  );
  const { data, setData, post, patch, processing, errors } = useForm({
    name:     rule?.name ?? '',
    context:  rule?.context ?? 'default',
    priority: rule?.priority ?? 100,
    continue: rule?.continue ?? false,
    enabled:  rule?.enabled ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const payload = { ...data, conditions: JSON.stringify(conditions), actions: JSON.stringify(actions) };
    if (isEdit) patch(`/pbx/dialplan-rules/${rule!.id}`, payload as any);
    else post('/pbx/dialplan-rules', payload as any);
  };

  const addCondition = () => setConditions([...conditions, { field: '', expression: '' }]);
  const removeCondition = (i: number) => setConditions(conditions.filter((_, idx) => idx !== i));
  const updateCondition = (i: number, key: keyof Condition, val: string) =>
    setConditions(conditions.map((c, idx) => idx === i ? { ...c, [key]: val } : c));
  const addAction = () => setActions([...actions, { application: '', data: '' }]);
  const removeAction = (i: number) => setActions(actions.filter((_, idx) => idx !== i));
  const updateAction = (i: number, key: string, val: string | boolean) =>
    setActions(actions.map((a, idx) => idx === i ? { ...a, [key]: val } : a));

  return (
    <AppLayout title={isEdit ? 'Edit Dialplan Rule' : 'New Dialplan Rule'}>
      <Head title={isEdit ? 'Edit Dialplan Rule' : 'New Dialplan Rule'} />
      <div className="nx-page-header flex items-center gap-3">
        <Link href="/pbx/dialplan-rules" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
        <div>
          <h1 className="nx-page-title">{isEdit ? `Edit: ${rule?.name}` : 'New Dialplan Rule'}</h1>
          <p className="nx-page-subtitle">Configure FreeSWITCH dialplan extension</p>
        </div>
      </div>
      <div className="p-6 max-w-3xl nx-animate-in">
        <form onSubmit={submit} className="space-y-6">
          {errors.validation && (
            <div className="p-3 rounded border border-red-500/40 bg-red-500/10 text-red-400 text-sm">
              {errors.validation}
            </div>
          )}
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <GitBranch className="w-4 h-4" style={{ color: 'var(--nx-green)' }} /> Rule Settings
            </h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Rule Name *</label>
                <input className={`nx-input ${errors.name ? 'error' : ''}`} value={data.name}
                  onChange={e => setData('name', e.target.value)} placeholder="e.g. outbound-local" />
                {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
              </div>
              <div>
                <label className="nx-label">Context</label>
                <input className="nx-input font-mono" value={data.context}
                  onChange={e => setData('context', e.target.value)} placeholder="default" />
              </div>
              <div>
                <label className="nx-label">Priority</label>
                <input type="number" className="nx-input" value={data.priority as number}
                  onChange={e => setData('priority', parseInt(e.target.value))} />
              </div>
              <div className="flex items-end gap-4">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" className="nx-checkbox" checked={data.continue as boolean}
                    onChange={e => setData('continue', e.target.checked)} />
                  <span className="nx-label mb-0">Continue matching</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" className="nx-checkbox" checked={data.enabled as boolean}
                    onChange={e => setData('enabled', e.target.checked)} />
                  <span className="nx-label mb-0">Enabled</span>
                </label>
              </div>
            </div>
          </div>
          <div className="nx-card p-6 space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-nx-primary">Conditions</h2>
              <button type="button" onClick={addCondition} className="nx-btn-ghost text-xs px-2 py-1">
                <Plus className="w-3 h-3 me-1" /> Add
              </button>
            </div>
            {conditions.map((c, i) => (
              <div key={i} className="flex gap-2 items-start">
                <input className="nx-input font-mono flex-1" value={c.field}
                  onChange={e => updateCondition(i, 'field', e.target.value)} placeholder="field" />
                <input className="nx-input font-mono flex-1" value={c.expression}
                  onChange={e => updateCondition(i, 'expression', e.target.value)} placeholder="regex" />
                <button type="button" onClick={() => removeCondition(i)} className="nx-btn-ghost p-2 text-red-400">
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            ))}
          </div>
          <div className="nx-card p-6 space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-nx-primary">Actions</h2>
              <button type="button" onClick={addAction} className="nx-btn-ghost text-xs px-2 py-1">
                <Plus className="w-3 h-3 me-1" /> Add
              </button>
            </div>
            {actions.map((a, i) => (
              <div key={i} className="flex gap-2 items-center">
                <input className="nx-input font-mono flex-1" value={a.application}
                  onChange={e => updateAction(i, 'application', e.target.value)} placeholder="application" />
                <input className="nx-input font-mono flex-1" value={a.data}
                  onChange={e => updateAction(i, 'data', e.target.value)} placeholder="data" />
                <label className="flex items-center gap-1 text-xs text-nx-muted cursor-pointer">
                  <input type="checkbox" className="nx-checkbox" checked={!!a.anti_action}
                    onChange={e => updateAction(i, 'anti_action', e.target.checked)} />
                  anti
                </label>
                <button type="button" onClick={() => removeAction(i)} className="nx-btn-ghost p-2 text-red-400">
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            ))}
          </div>
          <div className="flex gap-3">
            <button type="submit" disabled={processing} className="nx-btn-primary">
              <Save className="w-4 h-4" /> {processing ? 'Saving...' : 'Save Rule'}
            </button>
            <Link href="/pbx/dialplan-rules" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};
export default DialplanRuleForm;
