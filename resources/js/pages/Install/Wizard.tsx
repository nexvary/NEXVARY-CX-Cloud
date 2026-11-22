import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import {
  User, Building2, Phone, Hash, Users, Settings, CheckCircle,
  ChevronRight, ChevronLeft, Eye, EyeOff, Loader2, AlertCircle,
  Check, Server
} from 'lucide-react';

// ── Wizard Step Definitions ───────────────────────────────────────────────

const STEPS = [
  { id: 1, icon: User,      key: 'admin' },
  { id: 2, icon: Building2, key: 'organization' },
  { id: 3, icon: Server,    key: 'server' },
  { id: 4, icon: Phone,     key: 'provider' },
  { id: 5, icon: Hash,      key: 'numbers' },
  { id: 6, icon: Users,     key: 'extensions' },
  { id: 7, icon: Settings,  key: 'optional' },
  { id: 8, icon: CheckCircle, key: 'verify' },
  { id: 9, icon: Check,     key: 'complete' },
];

// ── Step 1: Admin ─────────────────────────────────────────────────────────

const StepAdmin: React.FC<{ onNext: (d: any) => void }> = ({ onNext }) => {
  const { t } = useTranslation();
  const { register, handleSubmit, watch, formState: { errors } } = useForm();
  const [showPass, setShowPass] = useState(false);

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-5">
      <div>
        <label className="nx-label">{t('install.admin_name')}</label>
        <input
          className={`nx-input ${errors.name ? 'error' : ''}`}
          {...register('name', { required: true, minLength: 2 })}
          placeholder={t('install.admin_name_placeholder')}
        />
      </div>
      <div>
        <label className="nx-label">{t('install.admin_email')}</label>
        <input
          className={`nx-input ${errors.email ? 'error' : ''}`}
          type="email"
          {...register('email', { required: true })}
          placeholder="admin@example.com"
        />
      </div>
      <div>
        <label className="nx-label">{t('install.admin_password')}</label>
        <div className="relative">
          <input
            className={`nx-input pe-10 ${errors.password ? 'error' : ''}`}
            type={showPass ? 'text' : 'password'}
            {...register('password', { required: true, minLength: 12 })}
          />
          <button
            type="button"
            onClick={() => setShowPass(!showPass)}
            className="absolute end-2.5 top-2 text-nx-muted"
          >
            {showPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
          </button>
        </div>
        <p className="text-xs text-nx-muted mt-1">{t('install.password_hint')}</p>
      </div>
      <div>
        <label className="nx-label">{t('install.admin_password_confirm')}</label>
        <input
          className={`nx-input ${errors.password_confirmation ? 'error' : ''}`}
          type="password"
          {...register('password_confirmation', {
            required: true,
            validate: v => v === watch('password'),
          })}
        />
      </div>
      <button type="submit" className="nx-btn-primary w-full">
        {t('common.next')} <ChevronRight className="w-4 h-4" />
      </button>
    </form>
  );
};

// ── Step 2: Organization ──────────────────────────────────────────────────

const StepOrganization: React.FC<{ onNext: (d: any) => void; onBack: () => void }> = ({ onNext, onBack }) => {
  const { t } = useTranslation();
  const { register, handleSubmit } = useForm();

  const timezones = Intl.supportedValuesOf?.('timeZone') ?? ['UTC', 'America/New_York', 'Europe/London', 'Asia/Dubai'];
  const languages = [
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'English' },
    { value: 'tr', label: 'Türkçe' },
    { value: 'es', label: 'Español' },
    { value: 'fr', label: 'Français' },
    { value: 'de', label: 'Deutsch' },
    { value: 'fa', label: 'فارسی' },
    { value: 'ur', label: 'اردو' },
  ];

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-5">
      <div>
        <label className="nx-label">{t('install.org_name')}</label>
        <input className="nx-input" {...register('org_name', { required: true })} />
      </div>
      <div>
        <label className="nx-label">{t('install.org_email')}</label>
        <input className="nx-input" type="email" {...register('org_email')} />
      </div>
      <div>
        <label className="nx-label">{t('install.timezone')}</label>
        <select className="nx-select" {...register('timezone')}>
          {timezones.slice(0, 50).map(tz => (
            <option key={tz} value={tz}>{tz}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="nx-label">{t('install.language')}</label>
        <select className="nx-select" {...register('locale')}>
          {languages.map(l => (
            <option key={l.value} value={l.value}>{l.label}</option>
          ))}
        </select>
      </div>
      <div className="flex gap-3">
        <button type="button" onClick={onBack} className="nx-btn-secondary flex-1">
          <ChevronLeft className="w-4 h-4" /> {t('common.back')}
        </button>
        <button type="submit" className="nx-btn-primary flex-1">
          {t('common.next')} <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </form>
  );
};

// ── Step 3: Server & Network ───────────────────────────────────────────────

const StepServer: React.FC<{ onNext: (d: any) => void; onBack: () => void; portChecks?: Record<string,boolean> }> = ({
  onNext, onBack, portChecks
}) => {
  const { t } = useTranslation();
  const { register, handleSubmit } = useForm({
    defaultValues: { domain: window.location.hostname, enable_https: false }
  });

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-5">
      <div>
        <label className="nx-label">{t('install.domain')}</label>
        <input className="nx-input font-mono" {...register('domain')} />
        <p className="text-xs text-nx-muted mt-1">{t('install.domain_hint')}</p>
      </div>
      <div className="flex items-center gap-3">
        <input type="checkbox" id="https" {...register('enable_https')} className="accent-crimson" />
        <label htmlFor="https" className="text-sm text-nx-secondary">{t('install.enable_https')}</label>
      </div>

      {portChecks && (
        <div className="nx-card p-4 space-y-2">
          <p className="text-xs font-medium text-nx-muted uppercase">{t('install.port_checks')}</p>
          {Object.entries(portChecks).map(([port, available]) => (
            <div key={port} className="flex items-center justify-between text-sm">
              <span className="font-mono text-nx-secondary">:{port}</span>
              <span className={`nx-badge ${available ? 'nx-badge-green' : 'nx-badge-red'}`}>
                {available ? t('install.port_available') : t('install.port_in_use')}
              </span>
            </div>
          ))}
        </div>
      )}

      <div className="flex gap-3">
        <button type="button" onClick={onBack} className="nx-btn-secondary flex-1">
          <ChevronLeft className="w-4 h-4" /> {t('common.back')}
        </button>
        <button type="submit" className="nx-btn-primary flex-1">
          {t('common.next')} <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </form>
  );
};

// ── Step 4: Provider ───────────────────────────────────────────────────────

const StepProvider: React.FC<{ onNext: (d: any) => void; onBack: () => void }> = ({ onNext, onBack }) => {
  const { t } = useTranslation();
  type ProviderFormData = {
    provider: string;
    telnyx_api_key?: string;
    telnyx_connection_id?: string;
    sip_host?: string;
    sip_port?: string;
    sip_username?: string;
    sip_password?: string;
  };
  const { register, handleSubmit, watch } = useForm<ProviderFormData>({ defaultValues: { provider: 'telnyx' } });
  const provider = watch('provider');
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<null | { ok: boolean; message: string }>(null);

  const testConnection = async (data: any) => {
    setTesting(true);
    try {
      const res = await fetch('/install/test-sip', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '' },
        body: JSON.stringify(data),
      });
      const json = await res.json();
      setTestResult({ ok: json.ok, message: json.message });
    } catch {
      setTestResult({ ok: false, message: t('install.connection_failed') });
    } finally {
      setTesting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit(onNext)} className="space-y-5">
      <div>
        <label className="nx-label">{t('install.provider')}</label>
        <select className="nx-select" {...register('provider')}>
          <option value="telnyx">Telnyx</option>
          <option value="generic">{t('install.generic_sip')}</option>
        </select>
      </div>

      {provider === 'telnyx' ? (
        <>
          <div>
            <label className="nx-label">{t('install.telnyx_api_key')}</label>
            <input className="nx-input font-mono text-sm" type="password" {...register('telnyx_api_key')} />
          </div>
          <div>
            <label className="nx-label">Connection Profile ID</label>
            <input className="nx-input font-mono text-sm" {...register('telnyx_connection_id')} />
          </div>
        </>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="nx-label">{t('install.sip_host')}</label>
              <input className="nx-input font-mono text-sm" {...register('sip_host')} />
            </div>
            <div>
              <label className="nx-label">{t('install.sip_port')}</label>
              <input className="nx-input font-mono text-sm" defaultValue="5060" {...register('sip_port')} />
            </div>
          </div>
          <div>
            <label className="nx-label">{t('install.sip_username')}</label>
            <input className="nx-input" {...register('sip_username')} />
          </div>
          <div>
            <label className="nx-label">{t('install.sip_password')}</label>
            <input className="nx-input" type="password" {...register('sip_password')} />
          </div>
        </>
      )}

      {testResult && (
        <div className={`flex items-start gap-2 p-3 rounded-lg text-sm ${
          testResult.ok ? 'bg-green-500/10 border border-green-500/30 text-green-400' :
                          'bg-red-500/10 border border-red-500/30 text-red-400'
        }`}>
          {testResult.ok ? <Check className="w-4 h-4 mt-0.5 shrink-0" /> : <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />}
          {testResult.message}
        </div>
      )}

      <div className="flex gap-3">
        <button type="button" onClick={onBack} className="nx-btn-secondary">
          <ChevronLeft className="w-4 h-4" />
        </button>
        <button
          type="button"
          className="nx-btn-secondary flex-1"
          onClick={() => handleSubmit(testConnection)()}
          disabled={testing}
        >
          {testing ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
          {t('install.test_connection')}
        </button>
        <button type="submit" className="nx-btn-primary flex-1">
          {t('common.next')} <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </form>
  );
};

// ── Step 8: Verification ──────────────────────────────────────────────────

const StepVerify: React.FC<{ checks: Array<{ name: string; status: 'ok' | 'fail' | 'pending' }> }> = ({ checks }) => {
  const { t } = useTranslation();
  return (
    <div className="space-y-3">
      {checks.map((check, i) => (
        <div key={i} className="flex items-center justify-between p-3 rounded-lg nx-card">
          <span className="text-sm text-nx-secondary">{check.name}</span>
          {check.status === 'ok'      && <span className="nx-badge nx-badge-green">{t('status.ok')}</span>}
          {check.status === 'fail'    && <span className="nx-badge nx-badge-red">{t('status.fail')}</span>}
          {check.status === 'pending' && <Loader2 className="w-4 h-4 text-nx-muted animate-spin" />}
        </div>
      ))}
    </div>
  );
};

// ── Main Wizard ────────────────────────────────────────────────────────────

interface WizardProps {
  token: string;
  portChecks: Record<string, boolean>;
  verifyChecks: Array<{ name: string; status: 'ok' | 'fail' | 'pending' }>;
}

const InstallWizard: React.FC<WizardProps> = ({ token, portChecks, verifyChecks }) => {
  const { t } = useTranslation();
  const [step, setStep] = useState(1);
  const [data, setData] = useState<Record<string, any>>({});
  const [submitting, setSubmitting] = useState(false);

  const handleNext = (stepData: any) => {
    setData(prev => ({ ...prev, ...stepData }));
    setStep(s => s + 1);
  };

  const handleBack = () => setStep(s => Math.max(1, s - 1));

  const handleFinish = async () => {
    setSubmitting(true);
    router.post('/install/complete', { ...data, token }, {
      onError: () => setSubmitting(false),
    });
  };

  return (
    <div className="min-h-screen bg-nx-base flex flex-col items-center justify-center p-6">
      {/* Logo */}
      <div className="mb-8 text-center">
        <div
          className="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-2xl font-black mx-auto mb-3"
          style={{ background: 'var(--nx-crimson)', boxShadow: 'var(--nx-glow-crimson)' }}
        >
          N
        </div>
        <h1 className="text-xl font-bold text-nx-primary">NEXVARY CX Cloud</h1>
        <p className="text-sm text-nx-muted mt-1">{t('install.setup_wizard')}</p>
      </div>

      {/* Step Progress */}
      <div className="flex items-center gap-1.5 mb-8">
        {STEPS.map((s, i) => {
          const Icon = s.icon;
          const done = step > s.id;
          const active = step === s.id;
          return (
            <React.Fragment key={s.id}>
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center transition-all ${
                  done   ? 'bg-nx-crimson text-white' :
                  active ? 'ring-2 ring-nx-crimson bg-nx-crimson/20 text-nx-crimson' :
                           'bg-nx-elevated text-nx-muted'
                }`}
                title={t(`install.step_${s.key}`)}
              >
                {done ? <Check className="w-3.5 h-3.5" /> : <Icon className="w-3.5 h-3.5" />}
              </div>
              {i < STEPS.length - 1 && (
                <div className={`h-0.5 w-6 rounded transition-all ${done ? 'bg-nx-crimson' : 'bg-nx-border'}`} />
              )}
            </React.Fragment>
          );
        })}
      </div>

      {/* Step Card */}
      <div className="nx-card-elevated w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-nx-primary mb-1">
          {t(`install.step_${STEPS[step - 1]?.key}_title`)}
        </h2>
        <p className="text-sm text-nx-muted mb-5">
          {t(`install.step_${STEPS[step - 1]?.key}_desc`)}
        </p>

        {step === 1 && <StepAdmin onNext={handleNext} />}
        {step === 2 && <StepOrganization onNext={handleNext} onBack={handleBack} />}
        {step === 3 && <StepServer onNext={handleNext} onBack={handleBack} portChecks={portChecks} />}
        {step === 4 && <StepProvider onNext={handleNext} onBack={handleBack} />}
        {step === 5 && (
          <div className="space-y-4">
            <p className="text-sm text-nx-secondary">{t('install.dids_later_hint')}</p>
            <div className="flex gap-3">
              <button onClick={handleBack} className="nx-btn-secondary flex-1"><ChevronLeft className="w-4 h-4" /></button>
              <button onClick={() => handleNext({})} className="nx-btn-primary flex-1">{t('install.skip_for_now')}</button>
            </div>
          </div>
        )}
        {step === 6 && (
          <div className="space-y-4">
            <p className="text-sm text-nx-secondary">{t('install.extensions_later_hint')}</p>
            <div className="flex gap-3">
              <button onClick={handleBack} className="nx-btn-secondary flex-1"><ChevronLeft className="w-4 h-4" /></button>
              <button onClick={() => handleNext({})} className="nx-btn-primary flex-1">{t('install.skip_for_now')}</button>
            </div>
          </div>
        )}
        {step === 7 && (
          <div className="space-y-4">
            <p className="text-sm text-nx-secondary">{t('install.optional_later_hint')}</p>
            <div className="flex gap-3">
              <button onClick={handleBack} className="nx-btn-secondary flex-1"><ChevronLeft className="w-4 h-4" /></button>
              <button onClick={() => handleNext({})} className="nx-btn-primary flex-1">{t('common.next')}<ChevronRight className="w-4 h-4" /></button>
            </div>
          </div>
        )}
        {step === 8 && (
          <div className="space-y-5">
            <StepVerify checks={verifyChecks} />
            <div className="flex gap-3">
              <button onClick={handleBack} className="nx-btn-secondary"><ChevronLeft className="w-4 h-4" /></button>
              <button onClick={handleFinish} className="nx-btn-primary flex-1" disabled={submitting}>
                {submitting ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                {t('install.complete')}
              </button>
            </div>
          </div>
        )}
        {step === 9 && (
          <div className="text-center space-y-4">
            <div className="w-16 h-16 rounded-full bg-green-500/10 flex items-center justify-center mx-auto">
              <Check className="w-8 h-8 text-green-400" />
            </div>
            <h3 className="text-lg font-semibold text-nx-primary">{t('install.complete_title')}</h3>
            <p className="text-sm text-nx-secondary">{t('install.complete_desc')}</p>
            <a href="/dashboard" className="nx-btn-primary w-full">{t('install.go_to_dashboard')}</a>
          </div>
        )}
      </div>

      <p className="mt-6 text-xs text-nx-muted">
        NEXVARY CX Cloud — <a href="https://nexvary.com" className="hover:text-nx-secondary">nexvary.com</a>
      </p>
    </div>
  );
};

export default InstallWizard;
