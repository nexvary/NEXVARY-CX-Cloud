# NEXVARY CX Cloud

**نظام اتصالات موحدة ذاتي الاستضافة**
**Self-Hosted Unified Communications Platform**

[![CI](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/workflows/ci.yml/badge.svg)](https://github.com/nexvary/NEXVARY-CX-Cloud/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![Node.js](https://img.shields.io/badge/Node.js-20+-green.svg)](https://nodejs.org)

---

NEXVARY CX Cloud is a professional, self-hosted unified communications platform combining PBX management, call center operations, CRM, billing, SMS, email, and Voice AI — all in one Nexvary-branded interface.

**Nexvary Inc. — Wyoming, USA — [nexvary.com](https://nexvary.com)**

---

## Quick Install

```bash
curl -fsSL https://install.nexvary.com | sudo bash
```

Or download and run:

```bash
sudo bash INSTALL-NEXVARY.sh
```

The installer sets up all dependencies on Debian 13 or Ubuntu 24.04 LTS, then opens a graphical setup wizard at `http://your-server/setup`.

---

## Platform Components

| Component | Technology | Status |
|-----------|-----------|--------|
| Web Management | Laravel 11 / PHP 8.3 | ✅ Stage 1 |
| Frontend UI | React 18 + TypeScript + Inertia.js | ✅ Stage 1 |
| Telephony Engine | FreeSWITCH | ✅ Stage 1 |
| PBX Config | FusionPBX-compatible XML | ✅ Stage 1 |
| Database | PostgreSQL 16 | ✅ Stage 1 |
| Cache / Queue | Redis | ✅ Stage 1 |
| WebRTC Bridge | Node.js WebSocket | ✅ Stage 1 |
| SIP Provider | Telnyx / Generic SIP | 🔄 Stage 4 |
| SMS Center | Telnyx SMS | 🔄 Stage 5 |
| Email Server | Postfix / Dovecot | 🔄 Stage 5 |
| CRM | Built-in | 🔄 Stage 5 |
| Billing Engine | Custom Rating | 🔄 Stage 6 |
| Voice AI | OpenAI / Pluggable | 🔄 Stage 7 |

---

## System Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| OS | Debian 12+ / Ubuntu 22.04+ | Debian 13 |
| CPU | 2 cores | 4+ cores |
| RAM | 2 GB | 4-8 GB |
| Disk | 20 GB | 40+ GB SSD |
| Architecture | x86_64 | x86_64 |

**Required ports:** 80, 443, 5060 (SIP UDP), 5061 (SIP TLS), 16384-32767 (RTP)

---

## Development Setup

```bash
# Clone
git clone https://github.com/nexvary/NEXVARY-CX-Cloud.git
cd NEXVARY-CX-Cloud

# PHP dependencies
composer install

# Node dependencies
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database (PostgreSQL required)
php artisan migrate

# Frontend dev
npm run dev

# Backend dev (separate terminal)
php artisan serve
```

---

## Architecture

```
nexvary-cx-cloud/
├── app/
│   ├── Http/Controllers/     # Laravel controllers (Auth, PBX, CRM, Billing…)
│   ├── Models/               # Eloquent models
│   └── Services/
│       ├── PBX/              # FreeSWITCH ESL + FusionPBX config
│       ├── Billing/          # Rating engine
│       ├── SMS/              # Telnyx SMS
│       ├── Email/            # IMAP/SMTP integration
│       └── AI/               # Voice AI adapters
├── database/migrations/      # PostgreSQL schema
├── resources/
│   ├── css/nexvary.css       # Nexvary design system
│   └── js/                   # React + TypeScript frontend
│       ├── layouts/          # AppLayout (header, sidebar)
│       └── pages/            # Inertia page components
├── rtc-bridge/               # Node.js WebRTC signaling bridge
├── installer/                # Setup wizard backend
├── INSTALL-NEXVARY.sh        # One-command installer
└── docs/                     # Documentation
```

---

## Documentation

- [ARCHITECTURE.md](docs/ARCHITECTURE.md) — System design
- [INSTALLATION.md](docs/INSTALLATION.md) — Detailed installation guide
- [SECURITY.md](docs/SECURITY.md) — Security hardening
- [PBX-ADMINISTRATION.md](docs/PBX-ADMINISTRATION.md) — PBX management
- [TELNYX-INTEGRATION.md](docs/TELNYX-INTEGRATION.md) — Telnyx setup
- [WEBRTC.md](docs/WEBRTC.md) — WebRTC configuration
- [CRM-BILLING.md](docs/CRM-BILLING.md) — CRM and billing

---

## Security

This platform is internet-facing telecom infrastructure. Before production deployment:

- Change all default credentials
- Enable HTTPS with valid certificate
- Configure firewall (only required ports open)
- Enable MFA for all administrators
- Review outbound calling restrictions
- Never expose FreeSWITCH ESL (port 8021) to internet

Report security issues to: security@nexvary.com

---

## About

**NEXVARY CX Cloud** is developed and maintained by **Nexvary Inc.**, Wyoming, USA.

- Website: [nexvary.com](https://nexvary.com)
- GitHub: [github.com/nexvary](https://github.com/nexvary)

This software includes components from:
- [FreeSWITCH](https://freeswitch.com) — MPL 1.1
- [Laravel](https://laravel.com) — MIT
- [React](https://react.dev) — MIT
- [FusionPBX](https://www.fusionpbx.com) — MPL 1.1 (configuration format reference)

---

*NEXVARY CX Cloud is proprietary software. All rights reserved. © Nexvary Inc.*
