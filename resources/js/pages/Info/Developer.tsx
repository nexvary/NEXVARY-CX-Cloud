import React from 'react';
import { Head } from '@inertiajs/react';
import { GitBranch, Globe, Code2, Package, ExternalLink, Terminal, BookOpen } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

const links = [
  {
    icon: Globe,
    label: 'Official Website',
    href: 'https://nexvary.com',
    display: 'nexvary.com',
    color: 'var(--nx-green)',
  },
  {
    icon: GitBranch,
    label: 'GitHub Repository',
    href: 'https://github.com/nexvary/NEXVARY-CX-Cloud',
    display: 'github.com/nexvary/NEXVARY-CX-Cloud',
    color: 'var(--nx-silver)',
  },
];

const techStack = [
  { name: 'Laravel 12',               role: 'Backend framework',               version: '^12.0' },
  { name: 'PHP 8.3',                   role: 'Runtime',                         version: '8.3+' },
  { name: 'React 18',                  role: 'UI library',                       version: '^18.0' },
  { name: 'Inertia.js 2',              role: 'SPA bridge',                       version: '^2.0' },
  { name: 'TypeScript',                role: 'Frontend type safety',             version: '^5.0' },
  { name: 'Tailwind CSS v4',           role: 'Styling',                          version: '^4.0' },
  { name: 'Vite 6',                    role: 'Frontend build',                   version: '^6.0' },
  { name: 'PostgreSQL 16',             role: 'Primary database',                 version: '16+' },
  { name: 'Redis 7',                   role: 'Cache / queue',                    version: '7+' },
  { name: 'FreeSWITCH',               role: 'PBX engine (ESL)',                  version: '1.10+' },
  { name: 'Spatie Permission v6',      role: 'RBAC',                             version: '^6.0' },
  { name: 'i18next',                   role: 'Internationalization (RTL/LTR)',   version: '^23.0' },
  { name: 'Recharts',                  role: 'Data visualization',               version: '^2.0' },
];

const DeveloperPage: React.FC = () => (
  <AppLayout title="About Developer">
    <Head title="About Developer" />

    <div className="nx-page-header">
      <h1 className="nx-page-title">About the Developer</h1>
      <p className="nx-page-subtitle">Project information, source code, and technology stack</p>
    </div>

    <div className="p-6 space-y-6 nx-animate-in">
      {/* Developer card — Corporate Seal + Company Info */}
      <div className="nx-card p-8">
        <div className="flex items-start gap-6 flex-wrap">
          {/* Corporate Seal */}
          <div className="flex flex-col items-center gap-2 shrink-0">
            <img
              src="/images/seal.webp"
              alt="NEXVARY Inc. Corporate Seal"
              className="w-32 h-32 rounded-full object-cover"
              style={{ boxShadow: '0 0 30px rgba(200,165,80,0.4), 0 0 60px rgba(200,165,80,0.15)' }}
            />
            <span className="text-xs" style={{ color: 'var(--nx-silver-dim)' }}>NEXVARY INC.</span>
          </div>

          <div className="flex-1 min-w-0">
            <h2 className="text-xl font-bold text-nx-primary">Nexvary Inc.</h2>
            <p className="text-xs uppercase tracking-widest mt-0.5 mb-3" style={{ color: 'var(--nx-silver-dim)' }}>
              Wyoming Corporation · United States of America
            </p>
            <p className="text-nx-secondary leading-relaxed">
              Nexvary develops open-source cloud communications infrastructure for
              telephony operators, contact centers, and enterprises. NEXVARY CX Cloud
              is the company's flagship self-hosted PBX and CX platform.
            </p>
            <div className="flex flex-wrap gap-3 mt-5">
              {links.map(link => (
                <a
                  key={link.href}
                  href={link.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all hover:scale-105"
                  style={{
                    background: 'var(--nx-bg-elevated)',
                    border: `1px solid var(--nx-border-active)`,
                    color: link.color,
                  }}
                >
                  <link.icon className="w-4 h-4" />
                  {link.display}
                  <ExternalLink className="w-3 h-3 opacity-60" />
                </a>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Repository info */}
      <div className="nx-card p-6">
        <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2 mb-4">
          <Code2 className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
          Repository
        </h2>
        <div className="space-y-3 text-sm">
          <div className="flex items-center gap-3 p-3 rounded-xl font-mono" style={{ background: 'var(--nx-bg-elevated)' }}>
            <Terminal className="w-4 h-4 text-nx-muted shrink-0" />
            <span className="text-nx-primary">git clone https://github.com/nexvary/NEXVARY-CX-Cloud.git</span>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
            {[
              { label: 'License',   value: 'MIT' },
              { label: 'Language',  value: 'PHP / TypeScript' },
              { label: 'Branch',    value: 'main' },
            ].map(item => (
              <div key={item.label} className="p-3 rounded-xl" style={{ background: 'var(--nx-bg-elevated)' }}>
                <p className="text-xs text-nx-muted uppercase tracking-wider mb-1">{item.label}</p>
                <p className="text-nx-primary font-medium">{item.value}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Tech stack table */}
      <div className="nx-card overflow-hidden">
        <div className="nx-page-header py-4 flex items-center gap-2">
          <Package className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
          <h3 className="text-sm font-semibold text-nx-primary">Technology Stack</h3>
        </div>
        <table className="nx-table">
          <thead>
            <tr>
              <th>Package / Tool</th>
              <th>Role</th>
              <th>Version</th>
            </tr>
          </thead>
          <tbody>
            {techStack.map(dep => (
              <tr key={dep.name}>
                <td className="font-mono text-sm text-nx-primary">{dep.name}</td>
                <td className="text-nx-secondary text-sm">{dep.role}</td>
                <td>
                  <span className="nx-badge nx-badge-blue font-mono text-xs">{dep.version}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Contributing */}
      <div className="nx-card p-6">
        <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2 mb-3">
          <BookOpen className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
          Contributing
        </h2>
        <p className="text-sm text-nx-secondary leading-relaxed">
          NEXVARY CX Cloud is open source. Contributions are welcome via pull requests on GitHub.
          Please open an issue first to discuss major changes. See the repository's{' '}
          <a
            href="https://github.com/nexvary/NEXVARY-CX-Cloud/blob/main/CONTRIBUTING.md"
            target="_blank"
            rel="noopener noreferrer"
            className="underline hover:text-green-400 transition-colors"
          >
            CONTRIBUTING.md
          </a>{' '}
          for guidelines.
        </p>
      </div>
    </div>
  </AppLayout>
);

export default DeveloperPage;
