import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { HelpCircle, Search, ChevronDown, Phone, Server, Layers, Users, CreditCard, Shield } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface FAQ {
  q_en: string;
  q_ar: string;
  a_en: string;
  a_ar: string;
  category: string;
}

const faqs: FAQ[] = [
  // SIP / Trunks
  {
    category: 'SIP Trunks',
    q_en: 'How do I add a SIP trunk?',
    q_ar: 'كيف أضيف خط SIP؟',
    a_en: 'Go to PBX → SIP Trunks → Add Trunk. Enter your provider\'s SIP server address, port (usually 5060), your username, and password. Enable "Register" if your provider requires registration. After saving, NEXVARY writes the gateway config to FreeSWITCH and reloads automatically.',
    a_ar: 'انتقل إلى PBX ← خطوط SIP ← إضافة خط. أدخل عنوان خادم SIP لمزودك، المنفذ (عادةً 5060)، اسم المستخدم وكلمة المرور. فعّل "التسجيل" إذا كان مزودك يتطلبه. بعد الحفظ، يكتب NEXVARY إعدادات البوابة إلى FreeSWITCH ويُعيد التحميل تلقائياً.',
  },
  {
    category: 'SIP Trunks',
    q_en: 'My trunk shows as "not registered" — how do I fix it?',
    q_ar: 'خط SIP لدي يظهر "غير مسجّل" — كيف أصلح ذلك؟',
    a_en: 'Check: (1) the SIP server address is correct, (2) the username/password match your provider\'s credentials exactly, (3) port 5060 UDP is not blocked by a firewall, (4) the "Register" toggle is ON. Use the "Reload" button on the trunk list to force FreeSWITCH to re-attempt registration.',
    a_ar: 'تحقق من: (1) صحة عنوان خادم SIP، (2) تطابق اسم المستخدم/كلمة المرور تماماً مع بيانات مزودك، (3) عدم حجب المنفذ 5060 UDP بجدار حماية، (4) تفعيل زر "التسجيل". استخدم زر "إعادة التحميل" في قائمة الخطوط لإجبار FreeSWITCH على إعادة محاولة التسجيل.',
  },
  // Extensions
  {
    category: 'Extensions',
    q_en: 'How do I register an extension on a SIP phone?',
    q_ar: 'كيف أسجّل امتداداً على هاتف SIP؟',
    a_en: 'After creating an extension (PBX → Extensions → Add), use these settings in your SIP client: Server: your NEXVARY server IP, Username: the extension number (e.g. 1001), Password: auto-generated (shown once — copy it). Domain: your server\'s IP or hostname. Protocol: UDP or TCP, port 5060.',
    a_ar: 'بعد إنشاء الامتداد (PBX ← الامتدادات ← إضافة)، استخدم هذه الإعدادات في عميل SIP: الخادم: عنوان IP لخادم NEXVARY، اسم المستخدم: رقم الامتداد (مثل 1001)، كلمة المرور: مُولَّدة تلقائياً (تظهر مرة واحدة — انسخها)، النطاق: IP خادمك أو اسم المضيف، البروتوكول: UDP أو TCP، المنفذ 5060.',
  },
  {
    category: 'Extensions',
    q_en: 'How do I enable call forwarding for an extension?',
    q_ar: 'كيف أُفعّل تحويل المكالمات لامتداد؟',
    a_en: 'Edit the extension (PBX → Extensions → Edit), enable "Call Forwarding" and enter the destination number. Alternatively, the user can dial *72 followed by the number from their SIP phone, and *73 to disable it.',
    a_ar: 'عدّل الامتداد (PBX ← الامتدادات ← تعديل)، فعّل "تحويل المكالمات" وأدخل رقم الوجهة. بديلاً، يمكن للمستخدم الاتصال بـ *72 يليه الرقم من هاتف SIP، و*73 لتعطيله.',
  },
  // IVR
  {
    category: 'IVR',
    q_en: 'How do I build an IVR menu?',
    q_ar: 'كيف أبني قائمة IVR؟',
    a_en: 'Go to PBX → IVR → New Menu. Enter a name and greeting text (text-to-speech). Add option mappings: key (0–9, # , *) → destination (extension, queue, another IVR). Set timeout and max failures. Connect the IVR to an inbound route under PBX → Inbound Routes.',
    a_ar: 'انتقل إلى PBX ← IVR ← قائمة جديدة. أدخل الاسم ونص الترحيب (تحويل نص إلى كلام). أضف تعيينات الخيارات: مفتاح (0-9، #، *) ← وجهة (امتداد، طابور، IVR آخر). حدد مهلة الانتظار والحد الأقصى للأخطاء. اربط IVR بمسار وارد ضمن PBX ← المسارات الواردة.',
  },
  // Queues
  {
    category: 'Call Queues',
    q_en: 'What call distribution strategies are available?',
    q_ar: 'ما استراتيجيات توزيع المكالمات المتاحة؟',
    a_en: 'NEXVARY supports: round-robin (each agent in turn), least-recent (longest-idle agent first), fewest-calls (agent with fewest answered calls), random, and ring-all (all agents simultaneously). Select the strategy when creating or editing a queue.',
    a_ar: 'يدعم NEXVARY: التناوب (كل وكيل بالدور)، الأقل حداثة (الوكيل الأطول خمولاً أولاً)، أقل المكالمات (الوكيل الأقل في المكالمات المُجابة)، عشوائي، والرنين للجميع (جميع الوكلاء في آنٍ واحد). اختر الاستراتيجية عند إنشاء الطابور أو تعديله.',
  },
  // Billing
  {
    category: 'Billing',
    q_en: 'How does the billing engine calculate costs?',
    q_ar: 'كيف يحسب محرك الفوترة التكاليف؟',
    a_en: 'Each call\'s destination number is matched against rate table prefixes (longest-prefix-match). The rate engine applies: (1) minimum duration, (2) billing increment (e.g. per 6 seconds), (3) rate per minute. Costs are calculated in the currency of the billing account\'s rate table.',
    a_ar: 'يُطابق رقم وجهة كل مكالمة بادئات جدول الأسعار (مطابقة أطول بادئة). يطبّق محرك التسعير: (1) الحد الأدنى لمدة المكالمة، (2) وحدة الفوترة (مثل كل 6 ثوانٍ)، (3) السعر لكل دقيقة. تُحسب التكاليف بعملة جدول الأسعار لحساب الفوترة.',
  },
  // Security
  {
    category: 'Security',
    q_en: 'How do I enable two-factor authentication (2FA)?',
    q_ar: 'كيف أُفعّل المصادقة الثنائية (2FA)؟',
    a_en: 'Go to Settings → Security and enable MFA Enforcement. Each admin will be prompted to scan a TOTP QR code on next login using an authenticator app (Google Authenticator, Authy, etc.). Codes expire every 30 seconds.',
    a_ar: 'انتقل إلى الإعدادات ← الأمان وفعّل إلزامية MFA. سيُطلب من كل مدير مسح رمز QR TOTP عند تسجيل الدخول التالي باستخدام تطبيق مصادقة (Google Authenticator، Authy، إلخ). تنتهي صلاحية الرموز كل 30 ثانية.',
  },
];

const categories = Array.from(new Set(faqs.map(f => f.category)));

const HelpFAQ: React.FC = () => {
  const [search, setSearch] = useState('');
  const [openIdx, setOpenIdx] = useState<number | null>(null);
  const [lang, setLang] = useState<'en' | 'ar'>('en');

  const filtered = faqs.filter(f => {
    const q = lang === 'ar' ? f.q_ar : f.q_en;
    const a = lang === 'ar' ? f.a_ar : f.a_en;
    const term = search.toLowerCase();
    return !term || q.toLowerCase().includes(term) || a.toLowerCase().includes(term) || f.category.toLowerCase().includes(term);
  });

  const catIcons: Record<string, React.ElementType> = {
    'SIP Trunks': Server,
    'Extensions': Phone,
    'IVR': Layers,
    'Call Queues': Users,
    'Billing': CreditCard,
    'Security': Shield,
  };

  return (
    <AppLayout title="Help & FAQ">
      <Head title="Help & FAQ" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">Help & FAQ</h1>
          <p className="nx-page-subtitle">Bilingual guides — English / العربية</p>
        </div>
        <div className="flex items-center gap-1 p-1 rounded-xl" style={{ background: 'var(--nx-bg-elevated)' }}>
          {(['en', 'ar'] as const).map(l => (
            <button
              key={l}
              onClick={() => setLang(l)}
              className="px-3 py-1.5 rounded-lg text-sm font-medium transition-all"
              style={
                lang === l
                  ? { background: 'var(--nx-green)', color: '#000' }
                  : { color: 'var(--var-text-secondary)' }
              }
            >
              {l === 'en' ? 'English' : 'العربية'}
            </button>
          ))}
        </div>
      </div>

      <div className="p-6 space-y-6 nx-animate-in" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
        {/* Search */}
        <div className="relative">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-nx-muted" />
          <input
            className="nx-input ps-10 text-base"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder={lang === 'ar' ? 'ابحث في الأسئلة الشائعة...' : 'Search FAQ...'}
          />
        </div>

        {/* Category sections */}
        {categories.map(cat => {
          const catFaqs = filtered.filter(f => f.category === cat);
          if (!catFaqs.length) return null;
          const Icon = catIcons[cat] ?? HelpCircle;
          return (
            <div key={cat}>
              <h2 className="text-sm font-semibold text-nx-primary flex items-center gap-2 mb-3">
                <Icon className="w-4 h-4" style={{ color: 'var(--nx-green)' }} />
                {cat}
              </h2>
              <div className="space-y-2">
                {catFaqs.map((faq, _i) => {
                  const idx = faqs.indexOf(faq);
                  const isOpen = openIdx === idx;
                  const q = lang === 'ar' ? faq.q_ar : faq.q_en;
                  const a = lang === 'ar' ? faq.a_ar : faq.a_en;
                  return (
                    <div key={idx} className="nx-card overflow-hidden">
                      <button
                        className="w-full text-start flex items-center justify-between p-4 hover:bg-nx-card-hover transition-colors"
                        onClick={() => setOpenIdx(isOpen ? null : idx)}
                      >
                        <span className="text-sm font-medium text-nx-primary">{q}</span>
                        <ChevronDown
                          className={`w-4 h-4 text-nx-muted shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                        />
                      </button>
                      {isOpen && (
                        <div
                          className="px-4 pb-4 text-sm text-nx-secondary leading-relaxed border-t border-nx pt-4"
                          style={{ background: 'var(--nx-bg-elevated)' }}
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

        {filtered.length === 0 && (
          <div className="nx-card p-12 text-center">
            <HelpCircle className="w-10 h-10 mx-auto mb-3 opacity-20" />
            <p className="text-nx-muted text-sm">
              {lang === 'ar' ? 'لا توجد نتائج لبحثك.' : 'No results for your search.'}
            </p>
          </div>
        )}
      </div>
    </AppLayout>
  );
};

export default HelpFAQ;
