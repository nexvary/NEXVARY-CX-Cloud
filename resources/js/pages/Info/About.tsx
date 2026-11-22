import React from 'react';
import { Head } from '@inertiajs/react';
import {
  Phone, Shield, Globe, Zap, Users, MessageSquare,
  CreditCard, Bot, Database, Code2
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const capabilities = [
  {
    icon: Phone,
    color: 'var(--nx-green)',
    title: 'PBX & SIP Trunking',
    desc: 'Full-featured IP-PBX built on FreeSWITCH. Manage SIP trunks, extensions, IVR menus, call queues, ring groups, and dialplan routing.',
  },
  {
    icon: Zap,
    color: 'var(--nx-warning)',
    title: 'Real-Time Call Control',
    desc: 'Monitor live calls, transfer, record, and terminate sessions through the ESL (Event Socket Library) API. Sub-second reaction time.',
  },
  {
    icon: Users,
    color: 'var(--nx-info)',
    title: 'Multi-Tenant Call Center',
    desc: 'ACD queues with configurable strategies (round-robin, least-recent, ring-all). Agent availability tracking and real-time wallboard.',
  },
  {
    icon: MessageSquare,
    color: 'var(--nx-green)',
    title: 'SMS & CRM Integration',
    desc: 'Two-way SMS via Telnyx. Unified CRM with contact management and support ticket tracking across voice, SMS, and email channels.',
  },
  {
    icon: CreditCard,
    color: 'var(--nx-warning)',
    title: 'Per-Second Billing Engine',
    desc: 'Configurable rate tables with prefix matching, per-second billing increments, and minimum duration rules. Multi-currency support.',
  },
  {
    icon: Bot,
    color: 'var(--nx-info)',
    title: 'Voice AI (beta)',
    desc: 'Plug LLM-based voice bots into your IVR for natural-language self-service. Webhook-driven architecture connects to any AI backend.',
  },
  {
    icon: Shield,
    color: 'var(--nx-error)',
    title: 'Security & Audit',
    desc: 'Role-based access control via Spatie Permissions. MFA enforcement, brute-force protection, and a full audit event log.',
  },
  {
    icon: Globe,
    color: 'var(--nx-green)',
    title: 'RTL-First Multilingual UI',
    desc: 'Full Arabic (RTL) and English (LTR) support with i18next. Tailwind logical properties ensure correct layout in both directions.',
  },
];

const architecture = [
  { label: 'Backend',    value: 'Laravel 12 / PHP 8.3',        color: 'var(--nx-info)' },
  { label: 'Frontend',  value: 'React 18 + Inertia.js 2 + TypeScript', color: 'var(--nx-warning)' },
  { label: 'Styling',   value: 'Tailwind CSS v4',              color: 'var(--nx-green)' },
  { label: 'Database',  value: 'PostgreSQL 16',                color: 'var(--nx-info)' },
  { label: 'Cache',     value: 'Redis 7',                      color: 'var(--nx-error)' },
  { label: 'PBX Core',  value: 'FreeSWITCH (ESL socket)',      color: 'var(--nx-green)' },
  { label: 'SMS',       value: 'Telnyx Messaging API',         color: 'var(--nx-warning)' },
  { label: 'Auth',      value: 'Spatie Permission v6 + TOTP',  color: 'var(--nx-info)' },
];

const limitations = [
  'Voice AI requires separate LLM API credentials — no built-in model is bundled.',
  'SMS delivery depends on the configured Telnyx account and DID numbers.',
  'FreeSWITCH must be installed and accessible on the same host or via ESL socket.',
  'Call recordings are stored locally; S3/cloud storage integration is a roadmap item.',
  'Billing engine calculates costs in-platform but does not process payments natively.',
];

const AboutSystem: React.FC = () => (
  <AppLayout title="About NEXVARY CX Cloud">
    <Head title="About System" />

    <div className="nx-page-header">
      <h1 className="nx-page-title">About NEXVARY CX Cloud</h1>
      <p className="nx-page-subtitle">Product capabilities, architecture, and known limitations</p>
    </div>

    <div className="p-6 space-y-8 nx-animate-in">
      {/* Hero */}
      <div className="nx-card p-8 text-center">
        <div
          className="w-16 h-16 rounded-2xl flex items-center justify-center text-black text-3xl font-black mx-auto mb-4"
          style={{ background: 'var(--nx-green)', boxShadow: 'var(--nx-glow-green)' }}
        >
          N
        </div>
        <h2 className="text-2xl font-bold text-nx-primary">NEXVARY CX Cloud</h2>
        <p className="text-nx-secondary mt-2 max-w-xl mx-auto">
          A complete, open-source cloud communications platform — PBX, call center, SMS/CRM,
          billing, and Voice AI — built for telecommunications operators and enterprises.
        </p>
        <div className="flex items-center justify-center gap-6 mt-5 text-sm text-nx-muted">
          <span>Version 1.0</span>
          <span>·</span>
          <a
            href="https://nexvary.com"
            target="_blank"
            rel="noopener noreferrer"
            className="hover:text-green-400 transition-colors"
          >
            nexvary.com
          </a>
        </div>
      </div>

      {/* Capabilities */}
      <div>
        <h2 className="text-lg font-semibold text-nx-primary mb-4">Platform Capabilities</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {capabilities.map(cap => (
            <div key={cap.title} className="nx-card p-5 hover:border-nx-active transition-all">
              <div
                className="w-9 h-9 rounded-xl flex items-center justify-center mb-3"
                style={{ background: `${cap.color}15` }}
              >
                <cap.icon className="w-5 h-5" style={{ color: cap.color }} />
              </div>
              <h3 className="text-sm font-semibold text-nx-primary mb-1">{cap.title}</h3>
              <p className="text-xs text-nx-muted leading-relaxed">{cap.desc}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Architecture */}
      <div className="nx-card p-6">
        <h2 className="text-lg font-semibold text-nx-primary mb-4 flex items-center gap-2">
          <Database className="w-5 h-5" style={{ color: 'var(--nx-green)' }} />
          Technology Stack
        </h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {architecture.map(item => (
            <div key={item.label} className="p-3 rounded-xl" style={{ background: 'var(--nx-bg-elevated)' }}>
              <p className="text-xs text-nx-muted uppercase tracking-wider mb-1">{item.label}</p>
              <p className="text-sm font-medium" style={{ color: item.color }}>{item.value}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Limitations */}
      <div className="nx-card p-6">
        <h2 className="text-lg font-semibold text-nx-primary mb-4 flex items-center gap-2">
          <Code2 className="w-5 h-5" style={{ color: 'var(--nx-warning)' }} />
          Known Limitations
        </h2>
        <ul className="space-y-2">
          {limitations.map((lim, i) => (
            <li key={i} className="flex items-start gap-2 text-sm text-nx-secondary">
              <span className="mt-0.5 w-1.5 h-1.5 rounded-full shrink-0" style={{ background: 'var(--nx-warning)' }} />
              {lim}
            </li>
          ))}
        </ul>
      </div>
    </div>
  </AppLayout>
);

export default AboutSystem;
