import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Trunk { id: string; name: string; sip_host: string; enabled: boolean }

interface InboundRoute {
  id?: string;
  name: string;
  did?: string;
  destination_type: string;
  destination: string;
  trunk_id?: string;
  priority: number;
  enabled: boolean;
}

interface OutboundRoute {
  id?: string;
  name: string;
  dial_pattern: string;
  trunk_id?: string;
  trunk_prefix?: string;
  priority: number;
  enabled: boolean;
}

type RouteData = InboundRoute | OutboundRoute;

interface Props {
  type: 'inbound' | 'outbound';
  route?: RouteData;
  trunks: Trunk[];
  readonly?: boolean;
}

const DESTINATION_TYPES = [
  { value: 'extension', label: 'Extension' },
  { value: 'ivr',       label: 'IVR Menu' },
  { value: 'queue',     label: 'Call Queue' },
  { value: 'ring_group',label: 'Ring Group' },
  { value: 'voicemail', label: 'Voicemail' },
];

const RouteForm: React.FC<Props> = ({ type, route, trunks, readonly }) => {
  const isInbound = type === 'inbound';
  const prefix    = isInbound ? '/pbx/inbound-routes' : '/pbx/outbound-routes';
  const isEditing = !!route?.id;
  const title     = isEditing
    ? (readonly ? 'View Route' : 'Edit Route')
    : (isInbound ? 'New Inbound Route' : 'New Outbound Route');

  const { data, setData, post, patch, processing, errors } = useForm<any>({
    name:             route?.name             ?? '',
    priority:         route?.priority         ?? 10,
    enabled:          route?.enabled          ?? true,
    trunk_id:         route?.trunk_id         ?? '',
    // Inbound-specific
    did:              (route as InboundRoute)?.did              ?? '',
    destination_type: (route as InboundRoute)?.destination_type ?? 'extension',
    destination:      (route as InboundRoute)?.destination      ?? '',
    // Outbound-specific
    dial_pattern:     (route as OutboundRoute)?.dial_pattern    ?? '',
    trunk_prefix:     (route as OutboundRoute)?.trunk_prefix    ?? '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing) {
      patch(`${prefix}/${route!.id}`);
    } else {
      post(prefix);
    }
  };

  return (
    <AppLayout title={title}>
      <Head title={title} />
      <div className="p-6 max-w-2xl space-y-6">
        <div className="flex items-center gap-3">
          <Link href={prefix} className="nx-btn-ghost p-1.5">
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <h1 className="text-xl font-bold" style={{ color: 'var(--nx-silver)' }}>{title}</h1>
        </div>

        <form onSubmit={handleSubmit} className="nx-card p-6 space-y-5">

          {/* Name */}
          <div>
            <label className="nx-label">Route Name</label>
            <input
              type="text"
              value={data.name}
              onChange={e => setData('name', e.target.value)}
              className="nx-input w-full"
              disabled={readonly}
              placeholder="e.g. Main line, US local calls"
            />
            {errors.name && <p className="nx-error">{errors.name}</p>}
          </div>

          {/* Inbound-specific */}
          {isInbound && (
            <>
              <div>
                <label className="nx-label">DID (called number)</label>
                <input
                  type="text"
                  value={data.did}
                  onChange={e => setData('did', e.target.value)}
                  className="nx-input w-full font-mono"
                  disabled={readonly}
                  placeholder="e.g. +15551234567 or leave blank for any"
                />
                {errors.did && <p className="nx-error">{errors.did}</p>}
              </div>

              <div>
                <label className="nx-label">Destination type</label>
                <select
                  value={data.destination_type}
                  onChange={e => setData('destination_type', e.target.value)}
                  className="nx-input w-full"
                  disabled={readonly}
                >
                  {DESTINATION_TYPES.map(dt => (
                    <option key={dt.value} value={dt.value}>{dt.label}</option>
                  ))}
                </select>
                {errors.destination_type && <p className="nx-error">{errors.destination_type}</p>}
              </div>

              <div>
                <label className="nx-label">Destination (extension number, queue ID, etc.)</label>
                <input
                  type="text"
                  value={data.destination}
                  onChange={e => setData('destination', e.target.value)}
                  className="nx-input w-full font-mono"
                  disabled={readonly}
                  placeholder="e.g. 1001"
                />
                {errors.destination && <p className="nx-error">{errors.destination}</p>}
              </div>
            </>
          )}

          {/* Outbound-specific */}
          {!isInbound && (
            <>
              <div>
                <label className="nx-label">Dial pattern (regex)</label>
                <input
                  type="text"
                  value={data.dial_pattern}
                  onChange={e => setData('dial_pattern', e.target.value)}
                  className="nx-input w-full font-mono"
                  disabled={readonly}
                  placeholder="e.g. ^1[2-9]\d{9}$"
                />
                {errors.dial_pattern && <p className="nx-error">{errors.dial_pattern}</p>}
              </div>

              <div>
                <label className="nx-label">Trunk prefix (optional)</label>
                <input
                  type="text"
                  value={data.trunk_prefix}
                  onChange={e => setData('trunk_prefix', e.target.value)}
                  className="nx-input w-full font-mono"
                  disabled={readonly}
                  placeholder="e.g. 9"
                />
                {errors.trunk_prefix && <p className="nx-error">{errors.trunk_prefix}</p>}
              </div>
            </>
          )}

          {/* Trunk selector (both) */}
          <div>
            <label className="nx-label">SIP Trunk</label>
            <select
              value={data.trunk_id}
              onChange={e => setData('trunk_id', e.target.value)}
              className="nx-input w-full"
              disabled={readonly}
            >
              <option value="">— None —</option>
              {trunks.map(t => (
                <option key={t.id} value={t.id}>{t.name} ({t.sip_host})</option>
              ))}
            </select>
            {errors.trunk_id && <p className="nx-error">{errors.trunk_id}</p>}
          </div>

          {/* Priority */}
          <div>
            <label className="nx-label">Priority</label>
            <input
              type="number"
              value={data.priority}
              onChange={e => setData('priority', parseInt(e.target.value, 10))}
              className="nx-input w-32"
              disabled={readonly}
              min={1}
              max={999}
            />
          </div>

          {/* Enabled */}
          <label className="flex items-center gap-3 cursor-pointer">
            <input
              type="checkbox"
              checked={data.enabled}
              onChange={e => setData('enabled', e.target.checked)}
              disabled={readonly}
              className="nx-checkbox"
            />
            <span className="text-sm text-nx-muted">Active</span>
          </label>

          {!readonly && (
            <div className="flex gap-3 pt-2">
              <button type="submit" disabled={processing} className="nx-btn-primary gap-2">
                <Save className="w-4 h-4" />
                {isEditing ? 'Update Route' : 'Create Route'}
              </button>
              <Link href={prefix} className="nx-btn-ghost">Cancel</Link>
            </div>
          )}
        </form>
      </div>
    </AppLayout>
  );
};

export default RouteForm;
