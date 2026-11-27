import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft, Settings } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface SipProfile {
  id?: string; name?: string; type?: string; sip_port?: number; sip_ip?: string;
  rtp_ip?: string; ext_sip_ip?: string; nat_enabled?: boolean;
  aggressive_nat_detection?: boolean; rtp_timeout?: number; rtp_hold_timeout?: number;
  codecs?: string[]; tls_enabled?: boolean; tls_port?: number; srtp_mode?: string;
  apply_inbound_acl?: string;
}
interface Props { profile?: SipProfile; defaultCodecs: string[]; }

const SipProfileForm: React.FC<Props> = ({ profile, defaultCodecs }) => {
  const isEdit = Boolean(profile?.id);
  const { data, setData, post, patch, processing, errors } = useForm({
    name: profile?.name ?? '',
    type: profile?.type ?? 'internal',
    sip_port: profile?.sip_port ?? 5060,
    sip_ip: profile?.sip_ip ?? 'auto',
    rtp_ip: profile?.rtp_ip ?? 'auto',
    ext_sip_ip: profile?.ext_sip_ip ?? '',
    nat_enabled: profile?.nat_enabled ?? false,
    aggressive_nat_detection: profile?.aggressive_nat_detection ?? false,
    rtp_timeout: profile?.rtp_timeout ?? 300,
    rtp_hold_timeout: profile?.rtp_hold_timeout ?? 1800,
    codecs: profile?.codecs ?? defaultCodecs,
    tls_enabled: profile?.tls_enabled ?? false,
    tls_port: profile?.tls_port ?? 5061,
    srtp_mode: profile?.srtp_mode ?? 'disabled',
    apply_inbound_acl: profile?.apply_inbound_acl ?? '',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) patch(`/pbx/sip-profiles/${profile!.id}`);
    else post('/pbx/sip-profiles');
  };

  const toggleCodec = (codec: string) => {
    const list = data.codecs as string[];
    setData('codecs', list.includes(codec) ? list.filter(c => c !== codec) : [...list, codec]);
  };

  return (
    <AppLayout title={isEdit ? 'Edit SIP Profile' : 'New SIP Profile'}>
      <Head title={isEdit ? 'Edit SIP Profile' : 'New SIP Profile'} />
      <div className="nx-page-header flex items-center gap-3">
        <Link href="/pbx/sip-profiles" className="nx-btn-ghost p-1.5"><ArrowLeft className="w-4 h-4" /></Link>
        <div>
          <h1 className="nx-page-title">{isEdit ? `Edit: ${profile?.name}` : 'New SIP Profile'}</h1>
          <p className="nx-page-subtitle">Configure FreeSWITCH sofia profile</p>
        </div>
      </div>
      <div className="p-6 max-w-3xl nx-animate-in">
        <form onSubmit={submit} className="space-y-6">
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2">
              <Settings className="w-4 h-4" style={{ color: 'var(--nx-green)' }} /> Basic Settings
            </h2>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="nx-label">Profile Name *</label>
                <input className={`nx-input ${errors.name ? 'error' : ''}`} value={data.name}
                  onChange={e => setData('name', e.target.value)} placeholder="e.g. internal" />
                {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
              </div>
              <div>
                <label className="nx-label">Profile Type</label>
                <select className="nx-input" value={data.type} onChange={e => setData('type', e.target.value as any)}>
                  <option value="internal">Internal</option>
                  <option value="external">External</option>
                </select>
              </div>
              <div>
                <label className="nx-label">SIP IP</label>
                <input className="nx-input font-mono" value={data.sip_ip}
                  onChange={e => setData('sip_ip', e.target.value)} placeholder="auto" />
              </div>
              <div>
                <label className="nx-label">SIP Port</label>
                <input type="number" className="nx-input" value={data.sip_port as number}
                  onChange={e => setData('sip_port', parseInt(e.target.value))} />
              </div>
              <div>
                <label className="nx-label">RTP IP</label>
                <input className="nx-input font-mono" value={data.rtp_ip}
                  onChange={e => setData('rtp_ip', e.target.value)} placeholder="auto" />
              </div>
              <div>
                <label className="nx-label">External SIP IP</label>
                <input className="nx-input font-mono" value={data.ext_sip_ip}
                  onChange={e => setData('ext_sip_ip', e.target.value)} placeholder="optional" />
              </div>
            </div>
          </div>
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary">NAT &amp; Timing</h2>
            <div className="grid grid-cols-2 gap-4">
              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" className="nx-checkbox" checked={data.nat_enabled as boolean}
                  onChange={e => setData('nat_enabled', e.target.checked)} />
                <span className="nx-label mb-0">Enable NAT</span>
              </label>
              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" className="nx-checkbox" checked={data.aggressive_nat_detection as boolean}
                  onChange={e => setData('aggressive_nat_detection', e.target.checked)} />
                <span className="nx-label mb-0">Aggressive NAT Detection</span>
              </label>
              <div>
                <label className="nx-label">RTP Timeout (sec)</label>
                <input type="number" className="nx-input" value={data.rtp_timeout as number}
                  onChange={e => setData('rtp_timeout', parseInt(e.target.value))} />
              </div>
              <div>
                <label className="nx-label">RTP Hold Timeout (sec)</label>
                <input type="number" className="nx-input" value={data.rtp_hold_timeout as number}
                  onChange={e => setData('rtp_hold_timeout', parseInt(e.target.value))} />
              </div>
            </div>
          </div>
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary">Codecs</h2>
            <div className="flex flex-wrap gap-2">
              {defaultCodecs.map(codec => (
                <button key={codec} type="button" onClick={() => toggleCodec(codec)}
                  className={`px-3 py-1 rounded text-xs font-mono border transition-colors ${
                    (data.codecs as string[]).includes(codec)
                      ? 'border-green-500 bg-green-500/10 text-green-400'
                      : 'border-nx text-nx-muted hover:border-nx-muted'
                  }`}>
                  {codec}
                </button>
              ))}
            </div>
          </div>
          <div className="nx-card p-6 space-y-4">
            <h2 className="text-sm font-semibold text-nx-primary">TLS / SRTP</h2>
            <div className="grid grid-cols-2 gap-4">
              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" className="nx-checkbox" checked={data.tls_enabled as boolean}
                  onChange={e => setData('tls_enabled', e.target.checked)} />
                <span className="nx-label mb-0">Enable TLS</span>
              </label>
              {data.tls_enabled && (
                <div>
                  <label className="nx-label">TLS Port</label>
                  <input type="number" className="nx-input" value={data.tls_port as number}
                    onChange={e => setData('tls_port', parseInt(e.target.value))} />
                </div>
              )}
              <div>
                <label className="nx-label">SRTP Mode</label>
                <select className="nx-input" value={data.srtp_mode} onChange={e => setData('srtp_mode', e.target.value as any)}>
                  <option value="disabled">Disabled</option>
                  <option value="optional">Optional</option>
                  <option value="mandatory">Mandatory</option>
                </select>
              </div>
              <div>
                <label className="nx-label">Inbound ACL</label>
                <input className="nx-input font-mono" value={data.apply_inbound_acl}
                  onChange={e => setData('apply_inbound_acl', e.target.value)} placeholder="optional ACL name" />
              </div>
            </div>
          </div>
          <div className="flex gap-3">
            <button type="submit" disabled={processing} className="nx-btn-primary">
              <Save className="w-4 h-4" /> {processing ? 'Saving...' : 'Save Profile'}
            </button>
            <Link href="/pbx/sip-profiles" className="nx-btn-ghost">Cancel</Link>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};
export default SipProfileForm;
