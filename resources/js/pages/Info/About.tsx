import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
  Search, ChevronDown, ChevronRight,
  Server, Phone, Layers, Users, MessageSquare, CreditCard, Shield, Bot,
  Download, Terminal, Settings, Globe, HelpCircle,
} from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

// ── Guide sections ────────────────────────────────────────────────────────────

interface Section {
  id: string;
  icon: React.ElementType;
  color: string;
  title_en: string;
  title_ar: string;
  items: { q_en: string; q_ar: string; a_en: string; a_ar: string }[];
}

const sections: Section[] = [
  {
    id: 'install',
    icon: Download,
    color: 'var(--nx-green)',
    title_en: 'Installation',
    title_ar: 'التثبيت',
    items: [
      {
        q_en: 'What are the server requirements?',
        q_ar: 'ما متطلبات الخادم؟',
        a_en: 'PHP 8.3+, PostgreSQL 16+, Redis 7+, FreeSWITCH 1.10+. Minimum 2 vCPUs and 4 GB RAM. Ubuntu 22.04 LTS or Debian 12 is recommended.',
        a_ar: 'PHP 8.3 أو أحدث، PostgreSQL 16 أو أحدث، Redis 7 أو أحدث، FreeSWITCH 1.10 أو أحدث. على الأقل 2 معالج وهمي و4 جيجابايت RAM. يُوصى بـ Ubuntu 22.04 LTS أو Debian 12.',
      },
      {
        q_en: 'How do I run the installation wizard?',
        q_ar: 'كيف أشغّل معالج التثبيت؟',
        a_en: 'Navigate to https://your-server/install. Enter the INSTALL_SECRET key from your .env file and follow the five-step wizard: requirements check, database, Redis, admin account, and confirmation.',
        a_ar: 'انتقل إلى https://your-server/install. أدخل مفتاح INSTALL_SECRET من ملف .env واتبع خطوات المعالج الخمس: فحص المتطلبات، قاعدة البيانات، Redis، حساب المسؤول، والتأكيد.',
      },
      {
        q_en: 'What happens after installation completes?',
        q_ar: 'ماذا يحدث بعد اكتمال التثبيت؟',
        a_en: 'The wizard writes the encrypted settings file, runs database migrations, creates the superadmin account, and creates the initial default tenant. You can then sign in and begin configuration.',
        a_ar: 'يكتب المعالج ملف الإعدادات المشفّر، يُنفّذ ترحيلات قاعدة البيانات، يُنشئ حساب المسؤول الأعلى والمستأجر الافتراضي الأول. يمكنك بعد ذلك تسجيل الدخول والبدء في الإعداد.',
      },
    ],
  },
  {
    id: 'pbx',
    icon: Phone,
    color: 'var(--nx-pink)',
    title_en: 'Telephony & Routing (PBX)',
    title_ar: 'الهاتفية والتوجيه',
    items: [
      {
        q_en: 'What is the PBX section?',
        q_ar: 'ما وظيفة قسم الهاتفية؟',
        a_en: 'The PBX section manages SIP trunks (carrier connections), extensions (internal phones), inbound routes (mapping incoming numbers to destinations), outbound routes (digit patterns for outgoing calls), IVR menus, call queues, ring groups, voicemail, and DID number inventory.',
        a_ar: 'يدير قسم الهاتفية خطوط SIP (وصلات المزوّد)، الامتدادات (الهواتف الداخلية)، مسارات الاتصال الواردة (ربط الأرقام الواردة بوجهات)، مسارات الاتصال الصادرة (أنماط الأرقام للمكالمات الصادرة)، قوائم IVR، طوابير المكالمات، مجموعات الرنين، البريد الصوتي، وقاعدة بيانات أرقام DID.',
      },
      {
        q_en: 'How do SIP trunks connect to FreeSWITCH?',
        q_ar: 'كيف تتصل خطوط SIP بـ FreeSWITCH؟',
        a_en: 'Each trunk you save writes a FreeSWITCH gateway XML configuration. NEXVARY calls sofia profile reload over the ESL socket so changes take effect without a full restart.',
        a_ar: 'يكتب كل خط تحفظه ملف XML لبوابة FreeSWITCH. يستدعي NEXVARY إعادة تحميل ملف sofia عبر مقبس ESL ليُطبَّق التغيير دون إعادة تشغيل كاملة.',
      },
    ],
  },
  {
    id: 'callcenter',
    icon: Users,
    color: 'var(--nx-blue)',
    title_en: 'Call Center',
    title_ar: 'مركز الاتصال',
    items: [
      {
        q_en: 'What does the Call Center section offer?',
        q_ar: 'ماذا يقدم قسم مركز الاتصال؟',
        a_en: 'Live call monitoring with the ability to intercept or transfer, a full CDR (call detail record) log with filtering, call recordings playback, and agent availability management.',
        a_ar: 'مراقبة المكالمات الحية مع إمكانية التدخل أو التحويل، سجل CDR كامل مع تصفية، تشغيل التسجيلات، وإدارة توفر الوكلاء.',
      },
      {
        q_en: 'How are call queues configured?',
        q_ar: 'كيف تُعدّ طوابير المكالمات؟',
        a_en: 'Create a queue under PBX → Call Queues. Assign a strategy (round-robin, least-recent, etc.), add agent extensions, and link the queue as the destination of an inbound route. Agents answer calls on their SIP extensions.',
        a_ar: 'أنشئ طابوراً ضمن الهاتفية → طوابير المكالمات. حدد استراتيجية التوزيع (تناوب، الأقل حداثة، إلخ)، أضف امتدادات الوكلاء، وربط الطابور وجهةً لمسار وارد. يرد الوكلاء على المكالمات عبر امتداداتهم.',
      },
    ],
  },
  {
    id: 'sms',
    icon: MessageSquare,
    color: 'var(--nx-blue)',
    title_en: 'Messages & Channels (SMS)',
    title_ar: 'الرسائل والقنوات',
    items: [
      {
        q_en: 'How does SMS work in NEXVARY CX Cloud?',
        q_ar: 'كيف تعمل الرسائل النصية في NEXVARY CX Cloud؟',
        a_en: 'SMS is powered by Telnyx. Incoming messages arrive via a signed webhook (Ed25519). The system resolves the tenant from the DID number ownership. Outbound messages are sent via the Telnyx API using the conversation\'s DID. Each message is logged to sms_messages with delivery status updates.',
        a_ar: 'تعمل الرسائل النصية عبر Telnyx. تصل الرسائل الواردة عبر webhook موقّع (Ed25519). يحدد النظام المستأجر من ملكية رقم DID. تُرسَل الرسائل الصادرة عبر API Telnyx باستخدام رقم DID المحادثة. تُسجَّل كل رسالة مع تحديثات حالة التسليم.',
      },
    ],
  },
  {
    id: 'crm',
    icon: Users,
    color: 'var(--nx-pink)',
    title_en: 'Customers & Support (CRM)',
    title_ar: 'العملاء والدعم',
    items: [
      {
        q_en: 'What is in the CRM section?',
        q_ar: 'ماذا يحتوي قسم إدارة العملاء؟',
        a_en: 'Contact management (name, email, phone), support ticket tracking across voice, SMS, and email channels, and an audit log of all CRM actions.',
        a_ar: 'إدارة جهات الاتصال (الاسم، البريد الإلكتروني، الهاتف)، تتبع تذاكر الدعم عبر قنوات الصوت والرسائل والبريد، وسجل تدقيق لجميع إجراءات CRM.',
      },
    ],
  },
  {
    id: 'billing',
    icon: CreditCard,
    color: 'var(--nx-green)',
    title_en: 'Billing & Reports',
    title_ar: 'الفوترة والتقارير',
    items: [
      {
        q_en: 'How does the billing engine work?',
        q_ar: 'كيف يعمل محرك الفوترة؟',
        a_en: 'Each call\'s destination matches rate table prefixes (longest-prefix match). The engine applies minimum duration, billing increment, and rate per minute. Costs are calculated in the billing account\'s currency. Reports show per-tenant call volumes and costs.',
        a_ar: 'تُطابق وجهة كل مكالمة بادئات جدول الأسعار (أطول بادئة). يطبق المحرك الحد الأدنى للمدة، وحدة الفوترة، والسعر لكل دقيقة. تُحسب التكاليف بعملة حساب الفوترة. تعرض التقارير حجم المكالمات والتكاليف لكل مستأجر.',
      },
    ],
  },
  {
    id: 'security',
    icon: Shield,
    color: 'var(--nx-warning)',
    title_en: 'Security',
    title_ar: 'الأمان',
    items: [
      {
        q_en: 'What security features are built in?',
        q_ar: 'ما ميزات الأمان المدمجة؟',
        a_en: 'Role-based access control (superadmin, admin, agent) via Spatie Permission. TOTP-based MFA enforcement. Brute-force login protection. Full audit event log per user. Customer data isolation (CX-012): each tenant\'s data is scoped and isolated from other tenants.',
        a_ar: 'التحكم في الوصول بالأدوار (مسؤول أعلى، مسؤول، وكيل) عبر Spatie Permission. فرض MFA بـ TOTP. حماية من القوة العمياء. سجل أحداث تدقيق كامل لكل مستخدم. عزل بيانات العملاء (CX-012): يُعزل بيانات كل مستأجر عن غيره.',
      },
    ],
  },
  {
    id: 'admin',
    icon: Settings,
    color: 'var(--nx-info)',
    title_en: 'Administration Guide',
    title_ar: 'دليل الإدارة',
    items: [
      {
        q_en: 'What is the Administration section?',
        q_ar: 'ما وظيفة قسم الإدارة؟',
        a_en: 'The /admin route is a superadmin-only configuration guide that provides read and draft access to all PBX and tenant modules. It is designed for initial setup before the full module routes are live. Superadmin must select a working tenant before saving records.',
        a_ar: 'مسار /admin هو دليل إعداد مخصص للمسؤول الأعلى فقط، يتيح قراءة وحفظ مسودات في جميع وحدات PBX والمستأجر. مصمم للإعداد الأولي قبل تفعيل مسارات الوحدات الكاملة. يجب على المسؤول الأعلى اختيار مستأجر عمل قبل حفظ السجلات.',
      },
      {
        q_en: 'How does multi-tenancy work?',
        q_ar: 'كيف يعمل نظام المستأجرين؟',
        a_en: 'Each tenant is an isolated organization. All tenant-scoped models automatically filter by tenant_id via a global Eloquent scope (TenantScope). Superadmin can switch between tenants using the tenant selector. Tenant users always see only their own data.',
        a_ar: 'كل مستأجر مؤسسة معزولة. تُصفَّى جميع النماذج المرتبطة بالمستأجر تلقائياً بـ tenant_id عبر نطاق Eloquent عام (TenantScope). يمكن للمسؤول الأعلى التبديل بين المستأجرين باستخدام منتقي المستأجر. يرى مستخدمو المستأجر بياناتهم فقط.',
      },
    ],
  },
  {
    id: 'voiceai',
    icon: Bot,
    color: 'var(--nx-pink)',
    title_en: 'Voice AI',
    title_ar: 'الذكاء الاصطناعي الصوتي',
    items: [
      {
        q_en: 'What is the Voice AI module?',
        q_ar: 'ما وحدة الذكاء الاصطناعي الصوتي؟',
        a_en: 'Voice AI plugs LLM-based voice bots into your IVR for natural-language self-service. It uses a webhook-driven architecture to connect to any AI backend. Each session is logged to ai_sessions. Requires separate LLM API credentials — no built-in model is bundled.',
        a_ar: 'تُدمج وحدة الذكاء الاصطناعي الصوتي روبوتات صوتية مبنية على نماذج لغوية كبيرة في قوائم IVR للخدمة الذاتية بلغة طبيعية. تستخدم بنية مدفوعة بـ webhook للاتصال بأي خلفية AI. تُسجَّل كل جلسة في ai_sessions. تتطلب بيانات اعتماد API للنموذج اللغوي المنفصل — لا يتضمن النظام نموذجاً مدمجاً.',
      },
    ],
  },
  {
    id: 'cli',
    icon: Terminal,
    color: 'var(--nx-info)',
    title_en: 'CLI & Maintenance',
    title_ar: 'سطر الأوامر والصيانة',
    items: [
      {
        q_en: 'What Artisan commands are available?',
        q_ar: 'ما أوامر Artisan المتاحة؟',
        a_en: 'php artisan migrate — run pending migrations. php artisan queue:work — start the job worker. php artisan nexvary:freeswitch-sync — push all trunk/extension configs to FreeSWITCH. The maintenance page at /admin/maintenance shows live service health.',
        a_ar: 'php artisan migrate — تشغيل الترحيلات المعلقة. php artisan queue:work — تشغيل عامل الطابور. php artisan nexvary:freeswitch-sync — دفع إعدادات الخطوط/الامتدادات إلى FreeSWITCH. تعرض صفحة الصيانة /admin/maintenance صحة الخدمات مباشرة.',
      },
    ],
  },
  {
    id: 'faq',
    icon: HelpCircle,
    color: 'var(--nx-warning)',
    title_en: 'Common Questions',
    title_ar: 'أسئلة شائعة',
    items: [
      {
        q_en: 'Can multiple admins manage the same tenant?',
        q_ar: 'هل يمكن لعدة مسؤولين إدارة نفس المستأجر؟',
        a_en: 'Yes. Create additional users under Settings → Users and assign them the admin or agent role within the tenant. All admin-role users see the same tenant data.',
        a_ar: 'نعم. أنشئ مستخدمين إضافيين ضمن الإعدادات → المستخدمون وعيّن لهم دور مسؤول أو وكيل داخل المستأجر. يرى جميع المستخدمين بدور مسؤول نفس بيانات المستأجر.',
      },
      {
        q_en: 'Is the platform production-ready?',
        q_ar: 'هل المنصة جاهزة للإنتاج؟',
        a_en: 'The core telephony, SMS, billing, and CRM modules are production-ready. Voice AI is in beta. Email channel integration is planned for a future phase.',
        a_ar: 'وحدات الهاتفية الأساسية والرسائل والفوترة وCRM جاهزة للإنتاج. وحدة الذكاء الاصطناعي الصوتي في مرحلة تجريبية. تكامل قناة البريد الإلكتروني مخطط لمرحلة مستقبلية.',
      },
    ],
  },
];

// ── About System page ─────────────────────────────────────────────────────────

const AboutSystem: React.FC = () => {
  const [search, setSearch] = useState('');
  const [openKey, setOpenKey] = useState<string | null>(null);
  const [lang, setLang] = useState<'en' | 'ar'>('en');

  const term = search.toLowerCase();

  const filteredSections = sections.map(sec => ({
    ...sec,
    items: sec.items.filter(item => {
      if (!term) return true;
      const q = lang === 'ar' ? item.q_ar : item.q_en;
      const a = lang === 'ar' ? item.a_ar : item.a_en;
      const t = lang === 'ar' ? sec.title_ar : sec.title_en;
      return q.toLowerCase().includes(term) || a.toLowerCase().includes(term) || t.toLowerCase().includes(term);
    }),
  })).filter(sec => sec.items.length > 0);

  return (
    <AppLayout title="About NEXVARY CX Cloud">
      <Head title="About System" />

      <div className="nx-page-header flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="nx-page-title">About NEXVARY CX Cloud</h1>
          <p className="nx-page-subtitle">Installation · Setup · Module guide · FAQ</p>
        </div>
        <div className="flex items-center gap-1 p-1 rounded-xl" style={{ background: 'var(--nx-bg-elevated)' }}>
          {(['en', 'ar'] as const).map(l => (
            <button
              key={l}
              onClick={() => setLang(l)}
              className="px-3 py-1.5 rounded-lg text-sm font-medium transition-all"
              style={lang === l ? { background: 'var(--nx-green)', color: '#000' } : { color: 'var(--nx-text-secondary)' }}
            >
              {l === 'en' ? 'English' : 'العربية'}
            </button>
          ))}
        </div>
      </div>

      <div className="p-6 space-y-5 nx-animate-in" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
        {/* Search */}
        <div className="relative">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-nx-muted" />
          <input
            className="nx-input ps-10 text-base"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder={lang === 'ar' ? 'ابحث في الدليل...' : 'Search the guide...'}
          />
        </div>

        {filteredSections.map(sec => {
          const SecIcon = sec.icon;
          return (
            <div key={sec.id}>
              {/* Section header */}
              <button
                className="w-full flex items-center gap-2 mb-2 text-sm font-semibold hover:opacity-80 transition-opacity"
                onClick={() => setOpenKey(openKey === sec.id ? null : sec.id)}
                aria-expanded={openKey === sec.id}
              >
                <div
                  className="w-6 h-6 rounded-lg flex items-center justify-center shrink-0"
                  style={{ background: `${sec.color}20` }}
                >
                  <SecIcon className="w-3.5 h-3.5" style={{ color: sec.color }} />
                </div>
                <span style={{ color: sec.color }}>{lang === 'ar' ? sec.title_ar : sec.title_en}</span>
                <ChevronDown
                  className={`w-4 h-4 ms-auto transition-transform text-nx-muted ${openKey === sec.id ? 'rotate-180' : ''}`}
                />
              </button>

              {/* Section items */}
              <div className={`space-y-2 ${openKey !== null && openKey !== sec.id && !term ? 'hidden' : ''}`}>
                {sec.items.map((item, idx) => {
                  const itemKey = `${sec.id}-${idx}`;
                  const isOpen = openKey === itemKey;
                  const q = lang === 'ar' ? item.q_ar : item.q_en;
                  const a = lang === 'ar' ? item.a_ar : item.a_en;
                  return (
                    <div key={itemKey} className="nx-card overflow-hidden">
                      <button
                        className="w-full text-start flex items-center gap-3 p-4 hover:bg-nx-card-hover transition-colors"
                        onClick={() => setOpenKey(isOpen ? sec.id : itemKey)}
                      >
                        <ChevronRight
                          className={`w-4 h-4 shrink-0 transition-transform ${isOpen ? 'rotate-90' : ''}`}
                          style={{ color: sec.color }}
                        />
                        <span className="text-sm font-medium text-nx-primary">{q}</span>
                      </button>
                      {isOpen && (
                        <div
                          className="px-4 pb-4 text-sm text-nx-secondary leading-relaxed border-t pt-4"
                          style={{ background: 'var(--nx-bg-elevated)', borderColor: 'var(--nx-border)' }}
                        >
                          {a}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          );
        })}

        {filteredSections.length === 0 && (
          <div className="nx-card p-12 text-center">
            <HelpCircle className="w-10 h-10 mx-auto mb-3 opacity-20" />
            <p className="text-nx-muted text-sm">
              {lang === 'ar' ? 'لا توجد نتائج لبحثك.' : 'No results for your search.'}
            </p>
          </div>
        )}

        {/* Platform footer */}
        <div className="nx-card p-5 flex items-center gap-4 flex-wrap">
          <img src="/images/seal.webp" alt="NEXVARY Inc." className="w-10 h-10 rounded-full object-cover shrink-0"
            style={{ boxShadow: '0 0 12px rgba(200,165,80,0.4)' }} />
          <div className="flex-1">
            <p className="text-sm font-semibold text-nx-primary">NEXVARY CX Cloud</p>
            <p className="text-xs text-nx-muted">Version 1.0 · MIT License</p>
          </div>
          <a href="https://nexvary.com" target="_blank" rel="noopener noreferrer"
            className="flex items-center gap-1 text-xs" style={{ color: 'var(--nx-green)' }}>
            <Globe className="w-3.5 h-3.5" />
            nexvary.com
          </a>
        </div>
      </div>
    </AppLayout>
  );
};

export default AboutSystem;
