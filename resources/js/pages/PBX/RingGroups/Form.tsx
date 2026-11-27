import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Member { id?: string; number: string; timeout: number }
interface RingGroup {
  id?: string; name: string; extension: string;
  strategy: string; timeout: number; destination_type?: string;
  destination?: string; enabled: boolean; members: Member[];
}
interface Props { ringGroup?: RingGroup }

const RingGroupForm: React.FC<Props> = ({ ringGroup }) => {
  const isEdit = Boolean(ringGroup?.id);
  const [members, setMembers] = useState<Member[]>(ringGroup?.members ?? []);

  const { data, setData, post, patch, processing, errors } = useForm({
    name: ringGroup?.name ?? '',
    extension: ringGroup?.extension ?? '',
    strategy: ringGroup?.strategy ?? 'simultaneous',
    timeout: ringGroup?.timeout ?? 30,
    destination_type: ringGroup?.destination_type ?? '',
    destination: ringGroup?.destination ?? '',
    enabled: ringGroup?.enabled ?? true,
    members: members.map(m => m.number),
  });

  const addMember = () => setMembers([...members, { number: '', timeout: 30 }]);
  const removeMember = (idx: number) => {
    const updated = members.filter((_, i) => i !== idx);
    setMembers(updated);
    setData('members', updated.map(m => m.number));
  };
  const updateMember = (idx: number, number: string) => {
    const updated = members.map((m, i) => i === idx ? { ...m, number } : m);
    setMembers(updated);
    setData('members', updated.map(m => m.number));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      patch(`/pbx/ring-groups/${ringGroup!.id}`);
    } else {
      post('/pbx/ring-groups');
    }
  };

  return (
    <AppLayout title={isEdit ? 'Edit Ring Group' : 'New Ring Group'}>
      <Head title={isEdit ? 'Edit Ring Group' : 'New Ring Group'} />
      <div className="p-6 max-w-2xl space-y-6">
        <div className="flex items-center gap-3">
          <Link href="/pbx/ring-groups" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>
            {isEdit ? 'Edit Ring Group' : 'New Ring Group'}
          </h1>
        </div>

        <form onSubmit={handleSubmit} className="nx-card p-6 space-y-5">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="nx-label">Name</label>
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
                <option value="simultaneous">Simultaneous</option>
                <option value="sequential">Sequential</option>
                <option value="ordered">Ordered</option>
              </select>
            </div>
            <div>
              <label className="nx-label">Group Timeout (seconds)</label>
              <input type="number" className="nx-input" value={data.timeout}
                     onChange={e => setData('timeout', parseInt(e.target.value))} min={10} max={300} />
            </div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="nx-label mb-0">Ring Members</label>
              <button type="button" onClick={addMember} className="nx-btn-ghost gap-1 text-xs">
                <Plus className="w-3.5 h-3.5" /> Add Member
              </button>
            </div>
            <div className="space-y-2">
              {members.map((m, idx) => (
                <div key={idx} className="flex items-center gap-2">
                  <input
                    className="nx-input flex-1 font-mono"
                    placeholder="Extension or +1234567890"
                    value={m.number}
                    onChange={e => updateMember(idx, e.target.value)}
                  />
                  <button type="button" onClick={() => removeMember(idx)} className="nx-btn-ghost p-2 text-red-400">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              ))}
              {members.length === 0 && (
                <p className="text-xs text-nx-muted">No members added yet.</p>
              )}
            </div>
          </div>

          <div className="flex items-center gap-2">
            <input type="checkbox" id="enabled" checked={data.enabled}
                   onChange={e => setData('enabled', e.target.checked)} className="rounded" />
            <label htmlFor="enabled" className="text-sm" style={{ color: 'var(--nx-silver)' }}>Enabled</label>
          </div>

          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={processing} className="nx-btn-primary gap-2">
              <Save className="w-4 h-4" />{isEdit ? 'Update' : 'Create'} Ring Group
            </button>
            <Link href="/pbx/ring-groups" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};

export default RingGroupForm;
