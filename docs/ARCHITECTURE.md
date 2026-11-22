# NEXVARY CX Cloud — Architecture

## Overview

NEXVARY CX Cloud is a self-hosted unified communications platform. Each customer deploys an independent instance on their own Linux server. No central Nexvary SaaS account is required.

## Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Customer VPS / Server                   │
│                                                              │
│  ┌──────────────┐  ┌──────────────────────────────────────┐ │
│  │   Browser    │  │        NEXVARY CX Cloud              │ │
│  │  (Admin UI)  │◄─┤  Laravel 11 + React 18 + Inertia     │ │
│  │  WebRTC      │  │  PHP 8.3  │  PostgreSQL  │  Redis     │ │
│  └──────────────┘  └────────────┬─────────────────────────┘ │
│                                  │ ESL (port 8021, internal) │
│  ┌────────────────────────────── ▼ ─────────────────────────┐│
│  │                   FreeSWITCH                              ││
│  │  SIP  │  RTP  │  IVR  │  Queues  │  Voicemail            ││
│  │  mod_verto (WebRTC)  │  mod_xml_curl                      ││
│  └────────────────────────────────────────────────────────── ┘│
│                                  │                            │
│  ┌───────────────┐  ┌────────────▼──────────────────────────┐│
│  │  RTC Bridge   │  │        SIP Provider                    ││
│  │  Node.js WS   │  │  Telnyx / Generic SIP Trunk            ││
│  └───────────────┘  └────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │  Optional Services                                       │ │
│  │  Postfix/Dovecot (email)  │  OpenAI (Voice AI)          │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## Technology Stack

| Layer | Technology | Justification |
|-------|-----------|--------------|
| Backend | PHP 8.3 / Laravel 11 | FusionPBX compatibility, mature ecosystem |
| Frontend | React 18 + TypeScript | Component model, TypeScript safety |
| SPA bridge | Inertia.js 2 | Laravel-React integration without API duplication |
| CSS framework | Tailwind CSS 4 + Nexvary tokens | Custom design system |
| Database | PostgreSQL 16 | ACID compliance, UUID support, JSON operators |
| Cache/Queue | Redis 7 | Real-time session state, background jobs |
| PBX | FreeSWITCH | Production-grade, ESL API, WebRTC support |
| PBX config format | FusionPBX XML layout | Proven configuration structure |
| WebRTC signaling | Node.js + ws | Lightweight tunnel between browser ↔ FreeSWITCH |
| Web server | Nginx | Reverse proxy, TLS termination, WebSocket support |
| Process manager | systemd | Service lifecycle management |
| SSL | Certbot / Let's Encrypt | Automated certificate management |

## Module Boundaries

Each module is independently toggleable. Core PBX operates without SMS, Email, or AI modules.

```
┌──────────────────────────────────────────────────────────────┐
│ Core Platform                                                 │
│  ├── Identity & Auth (Laravel Sanctum + RBAC + MFA)         │
│  ├── Organization Settings                                   │
│  └── Audit Logs                                             │
├──────────────────────────────────────────────────────────────┤
│ PBX Module                                                   │
│  ├── Extensions & SIP accounts                              │
│  ├── Trunks & Gateways                                      │
│  ├── Dialplan (inbound/outbound routes)                     │
│  ├── IVR builder                                             │
│  ├── Call queues & ring groups                              │
│  ├── Voicemail                                               │
│  └── FreeSWITCH ESL integration                             │
├──────────────────────────────────────────────────────────────┤
│ WebRTC Module (optional, default: enabled)                   │
│  ├── Node.js signaling bridge                               │
│  └── SIP.js browser softphone                               │
├──────────────────────────────────────────────────────────────┤
│ SMS Module (requires Telnyx)                                 │
├──────────────────────────────────────────────────────────────┤
│ Email Module (requires Postfix/Dovecot)                      │
├──────────────────────────────────────────────────────────────┤
│ CRM Module                                                   │
│  ├── Contacts & Companies                                   │
│  └── Support Tickets                                         │
├──────────────────────────────────────────────────────────────┤
│ Billing Module                                               │
│  ├── Rating Engine                                           │
│  ├── Rate Tables                                             │
│  └── Invoicing                                              │
├──────────────────────────────────────────────────────────────┤
│ Voice AI Module (requires AI provider key)                   │
│  ├── Speech-to-Text                                         │
│  ├── Text-to-Speech                                         │
│  └── Conversation Engine                                    │
└──────────────────────────────────────────────────────────────┘
```

## FreeSWITCH Integration

NEXVARY writes standard FreeSWITCH XML configuration files in the FusionPBX directory layout:

- Extensions: `/etc/freeswitch/directory/default/{extension}.xml`
- Gateways: `/etc/freeswitch/sip_profiles/external/nexvary-trunk-{id}.xml`
- Dialplan: `/etc/freeswitch/dialplan/default/` and `/public/`
- IVR: `/etc/freeswitch/autoload_configs/ivr.conf.d/`

Configuration changes are applied via `reloadxml` through the ESL API (port 8021, localhost-only).

**FreeSWITCH ESL is never exposed to the internet.** All ESL communication is from the Laravel application on localhost.

## Security Architecture

- All web traffic over HTTPS (nginx → php-fpm)
- SIP over TLS (port 5061) for SIP clients where supported
- SRTP for media where supported
- Strong authentication + MFA for administrators
- Role-based access: superadmin, admin, supervisor, agent
- fail2ban for SIP brute-force and HTTP abuse
- ufw firewall blocking ESL (8021) from internet
- Encrypted storage for SIP credentials and API keys
- Audit log for all administrative actions

## Data Flow: Inbound Call

```
PSTN → SIP Provider → FreeSWITCH (port 5060/5061)
  → Dialplan lookup (inbound route)
  → IVR / Queue / Extension
  → Agent's SIP phone or browser WebRTC
     (Browser ↔ RTC Bridge (ws:3000) ↔ FreeSWITCH mod_verto)
  → Call record written to PostgreSQL via ESL event handler
  → Real-time dashboard update via Redis pub/sub → WebSocket → browser
```

## Configuration Source of Truth

One authoritative configuration path: NEXVARY CX Cloud writes XML files; administrators never need to edit XML directly. The only FreeSWITCH configuration that exists outside NEXVARY is core FreeSWITCH defaults (sofia profile transports, codec priority, etc.) which are set during installation.
