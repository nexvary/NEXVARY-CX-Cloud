import { Head } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    ArrowLeft, ArrowRight, Check, CircleAlert, Database, KeyRound,
    ListChecks, LoaderCircle, Phone, Server, ShieldCheck, UserRound,
} from 'lucide-react';
import axios from 'axios';

type Connection = { host: string; port: string; user: string; password: string; name?: string };
type Result = { ok: boolean; checks?: Record<string, boolean>; problem?: string; login?: string; errors?: Record<string, string[]> };
type Props = { setupEnabled: boolean; secureAccess: boolean; database: Omit<Connection, 'password'>; redis: Omit<Connection, 'password'> };
const steps = ['welcome', 'readiness', 'database', 'redis', 'freeswitch', 'admin', 'review', 'success'] as const;
const icons = [ShieldCheck, ListChecks, Database, Server, Phone, UserRound, KeyRound, Check];
const fieldTypes: Record<string, string> = { port: 'number', password: 'password', email: 'email', password_confirmation: 'password' };

export default function Wizard({ setupEnabled, secureAccess, database, redis }: Props) {
    const { t, i18n } = useTranslation();
    const [position, setPosition] = useState(0);
    const [token, setToken] = useState('');
    const [db, setDb] = useState({ ...database, password: '' });
    const [cache, setCache] = useState({ ...redis, password: '' });
    const [admin, setAdmin] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [result, setResult] = useState<Result>();
    const [busy, setBusy] = useState(false);
    const [pbx, setPbx] = useState<boolean>();
    const [login, setLogin] = useState('/login');
    const step = steps[position];
    const Icon = icons[position];
    const previous = i18n.dir() === 'rtl' ? ArrowRight : ArrowLeft;
    const next = i18n.dir() === 'rtl' ? ArrowLeft : ArrowRight;
    const Previous = previous;
    const Next = next;
    const copy = (key: string) => t(`setup.${key}`);

    const go = (index: number) => {
        setResult(undefined);
        setPosition(index);
    };

    async function request(path: string, data: Record<string, unknown> = {}): Promise<Result> {
        setBusy(true);
        setResult(undefined);
        try {
            const response = await axios.post<Result>(`/setup/${path}`, data, {
                headers: { Accept: 'application/json', 'X-Setup-Token': token },
            });
            setResult(response.data);
            return response.data;
        } catch (error) {
            const response = axios.isAxiosError<Result>(error) ? error.response : undefined;
            const failed = {
                ok: false,
                problem: response?.status === 403 ? 'token' : response?.data?.problem ?? 'request',
                errors: response?.data?.errors,
            };
            setResult(failed);
            return failed;
        } finally {
            setBusy(false);
        }
    }

    async function testConnection() {
        const data = step === 'database' ? { database: db } : step === 'redis' ? { redis: cache } : {};
        const tested = await request(`connection/${step}`, data);
        if (step === 'freeswitch') {
            setPbx(tested.ok);
        }
    }

    async function finish() {
        const completed = await request('complete', { database: db, redis: cache, admin, locale: i18n.language });
        if (completed.ok) {
            setLogin(completed.login ?? '/login');
            setToken('');
            setDb({ ...db, password: '' });
            setCache({ ...cache, password: '' });
            setAdmin({ name: '', email: '', password: '', password_confirmation: '' });
            go(7);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (step === 'welcome') {
            go(1);
            void request('readiness');
        } else if (step === 'database' || step === 'redis' || step === 'freeswitch') {
            void testConnection();
        } else if (step === 'admin') {
            if (admin.password !== admin.password_confirmation || new TextEncoder().encode(admin.password).length > 72) {
                setResult({ ok: false, problem: 'password' });
                return;
            }
            go(6);
        } else if (step === 'review') {
            void finish();
        }
    }

    function field(name: string, value: string, change: (value: string) => void, required = true, prefix = '') {
        const error = result?.errors?.[prefix + name]?.[0];
        return <label key={name} className="grid min-w-0 gap-1.5 text-sm font-medium">
            {copy(name)}
            <input name={name} className="nx-input" value={value} onChange={event => change(event.target.value)}
                type={fieldTypes[name] ?? 'text'} required={required} dir={name === 'name' && step === 'admin' ? 'auto' : 'ltr'}
                spellCheck={false} autoComplete={fieldTypes[name] === 'password' ? 'new-password' : 'off'}
                min={name === 'port' ? 1 : undefined} max={name === 'port' ? 65535 : undefined}
                minLength={step === 'admin' && name === 'password' ? 12 : undefined} aria-invalid={!!error} />
            {error && <span className="text-red-300 text-xs">{copy('invalid_field')}</span>}
        </label>;
    }

    const connection = step === 'database' ? db : cache;
    const updateConnection = step === 'database' ? setDb : setCache;
    const canAdvance = result?.ok || (step === 'freeswitch' && pbx !== undefined);
    const testsConnection = step === 'database' || step === 'redis' || step === 'freeswitch';
    const rendersSubmit = ['welcome', 'database', 'redis', 'freeswitch', 'admin', 'review'].includes(step);

    return <main className="nx-setup-shell">
        <Head title={copy('title')} />
        <div className="mx-auto w-full max-w-2xl">
            <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="nx-brand-mark" aria-hidden="true">N</span>
                    <div>
                        <p className="font-bold tracking-wide" dir="ltr">NEXVARY CX Cloud</p>
                        <p className="text-sm nx-quiet">{copy('title')}</p>
                    </div>
                </div>
                <label className="sr-only" htmlFor="setup-locale">{copy('language')}</label>
                <select id="setup-locale" className="nx-select !w-auto" value={i18n.language} onChange={event => {
                    const locale = event.target.value;
                    document.documentElement.lang = locale;
                    document.documentElement.dir = i18n.dir(locale);
                    void i18n.changeLanguage(locale);
                }}>
                    <option value="ar">العربية</option>
                    <option value="en">English</option>
                </select>
            </header>
            <div className="nx-card nx-setup-card p-5 sm:p-8">
                <div className="mb-5">
                    <p className="mb-2 text-sm nx-quiet">{t('setup.progress', { current: position + 1, total: steps.length })}</p>
                    <div className="grid grid-cols-8 gap-1" role="progressbar" aria-label={copy('title')}
                        aria-valuemin={1} aria-valuemax={steps.length} aria-valuenow={position + 1}>
                        {steps.map((key, index) => <span key={key} className={index <= position ? 'nx-progress-done' : 'nx-progress-pending'} />)}
                    </div>
                </div>
                <div className="mb-5">
                    <Icon aria-hidden="true" className="mb-3 size-7 text-sky-300" />
                    <h1 id="setup-heading" tabIndex={-1} className="text-2xl font-semibold">{copy(step)}</h1>
                    <p className="mt-2 text-sm nx-quiet">{copy(`${step}_help`)}</p>
                </div>
                <form onSubmit={submit} className="grid gap-4" aria-labelledby="setup-heading">
                    {step === 'welcome' && (setupEnabled && secureAccess
                        ? <label className="grid gap-1.5 text-sm font-medium">{copy('token')}
                            <input name="token" className="nx-input" type="password" dir="ltr" required autoComplete="off"
                                value={token} onChange={event => setToken(event.target.value)} />
                        </label>
                        : <p role="alert" className="nx-notice nx-notice-error">{copy(secureAccess ? 'token_missing' : 'fix_https')}</p>)}
                    {step === 'readiness' && result?.checks && <ul className="grid gap-2">
                        {Object.entries(result.checks).map(([key, passed]) => <li key={key} className="nx-notice flex items-start gap-2">
                            {passed ? <Check className="size-5 shrink-0 text-emerald-300" /> : <CircleAlert className="size-5 shrink-0 text-red-300" />}
                            <span>{copy(`check_${key}`)}{!passed && <small className="block nx-quiet">{copy(`fix_${key}`)}</small>}</span>
                        </li>)}
                    </ul>}
                    {(step === 'database' || step === 'redis') && <div className="grid gap-3 sm:grid-cols-2">
                        {(['host', 'port', ...(step === 'database' ? ['name'] : []), 'user', 'password'] as (keyof Connection)[]).map(name =>
                            field(name, connection[name] ?? '', value => {
                                updateConnection({ ...connection, [name]: value });
                                setResult(undefined);
                            }, name !== 'password' && (name !== 'user' || step === 'database'), `${step}.`))}
                    </div>}
                    {step === 'admin' && <div className="grid gap-3 sm:grid-cols-2">
                        {(Object.keys(admin) as (keyof typeof admin)[]).map(name =>
                            field(name, admin[name], value => setAdmin({ ...admin, [name]: value }), true, 'admin.'))}
                    </div>}
                    {step === 'review' && <ul className="nx-notice grid gap-2 text-sm">
                        <li>{copy('database')}{' · '}<bdi>{db.host}:{db.port} / {db.name}</bdi></li>
                        <li>{copy('redis')}{' · '}<bdi>{cache.host}:{cache.port}</bdi></li>
                        <li>{copy('admin')}{' · '}<bdi>{admin.email}</bdi></li>
                        <li>{copy('freeswitch')}{' · '}{copy(pbx ? 'connected' : 'pbx_pending')}</li>
                    </ul>}
                    {busy && <p data-testid="working" role="status" className="nx-notice flex items-center gap-2">
                        <LoaderCircle className="size-4 animate-spin" />{copy(step === 'review' ? 'installing' : 'checking')}
                    </p>}
                    {result && !result.checks && <p data-testid="result" data-ok={result.ok} role={result.ok ? 'status' : 'alert'}
                        className={`nx-notice ${result.ok ? 'nx-notice-success' : 'nx-notice-error'}`}>
                        {result.ok ? copy('connected') : copy(result.problem ? `error_${result.problem}` : `error_${step}`)}
                    </p>}
                    <div className="flex flex-wrap items-center gap-2">
                        {position > 0 && step !== 'success' && <button type="button" className="nx-btn-secondary" disabled={busy}
                            onClick={() => go(position - 1)}><Previous className="size-4" />{copy('back')}</button>}
                        {step === 'readiness' && <button type="button" className="nx-btn-secondary" disabled={busy}
                            onClick={() => void request('readiness')}>{copy('retry')}</button>}
                        {rendersSubmit && <button type="submit" className="nx-btn-primary" disabled={busy || (step === 'welcome' && (!setupEnabled || !secureAccess))}>
                            {copy(testsConnection ? 'test' : step === 'review' ? result ? 'retry' : 'finish' : 'next')}
                            {!testsConnection && step !== 'review' && <Next className="size-4" />}
                        </button>}
                        {(testsConnection || step === 'readiness') && canAdvance && <button type="button" className="nx-btn-primary"
                            disabled={busy} onClick={() => go(position + 1)}>
                            {copy(step === 'freeswitch' && !pbx ? 'defer' : 'next')}<Next className="size-4" />
                        </button>}
                        {step === 'success' && <a className="nx-btn-primary" href={login}>{copy('login')}</a>}
                    </div>
                </form>
            </div>
        </div>
    </main>;
}
