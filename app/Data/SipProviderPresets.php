<?php

namespace App\Data;

/**
 * Versioned built-in SIP provider presets.
 *
 * Each entry carries:
 *   - documented defaults per auth_mode
 *   - which fields the user must supply vs. which come from the preset
 *   - a link to the provider's official configuration documentation
 *
 * Stored version strings allow NEXVARY to detect when a preset has been
 * updated and prompt administrators to review existing gateways.
 */
class SipProviderPresets
{
    public const VERSION = '2024-11';

    public static function all(): array
    {
        return [
            'telnyx'     => static::telnyx(),
            'twilio'     => static::twilio(),
            'signalwire' => static::signalwire(),
            'voicetel'   => static::voicetel(),
            'generic'    => static::generic(),
        ];
    }

    public static function get(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(static::all());
    }

    // ── Telnyx ────────────────────────────────────────────────────────────────

    private static function telnyx(): array
    {
        return [
            'key'        => 'telnyx',
            'label'      => 'Telnyx',
            'version'    => static::VERSION,
            'docs_url'   => 'https://support.telnyx.com/en/articles/3220393-fusionpbx-telnyx-credentials',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.telnyx.com',
                    'sip_port'          => 5061,
                    'transport'         => 'tls',
                    'realm'             => 'sip.telnyx.com',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            // Fields derived from preset (shown read-only unless user overrides)
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'realm', 'register'],
            // Fields the user must supply
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter the SIP Connection username and password from your Telnyx portal '
                      . '(Mission Control → Voice → SIP Connections → your connection → Credentials tab).',
                'ar' => 'أدخل اسم مستخدم SIP وكلمة المرور من بوابة Telnyx '
                      . '(Mission Control → Voice → SIP Connections → اتصالك → تبويب Credentials).',
            ],
        ];
    }

    // ── Twilio Elastic SIP Trunking ───────────────────────────────────────────

    private static function twilio(): array
    {
        return [
            'key'        => 'twilio',
            'label'      => 'Twilio Elastic SIP Trunking',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.twilio.com/docs/sip-trunking',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            // Twilio's Termination SIP URI is account-specific — user must supply
            'preset_fields'   => ['sip_port', 'transport', 'register'],
            'required_fields' => ['sip_host'],
            'instructions'    => [
                'en' => 'Enter the Termination SIP URI from your Twilio Elastic SIP Trunk '
                      . '(Twilio Console → Elastic SIP Trunks → your trunk → Termination → SIP URI). '
                      . 'For credential auth, also enter a Credential List username and password. '
                      . 'For IP ACL, add this server\'s IP to your Twilio Trunk\'s IP Access Control List.',
                'ar' => 'أدخل Termination SIP URI من واجهة Twilio '
                      . '(Twilio Console → Elastic SIP Trunks → الخط → Termination → SIP URI). '
                      . 'للمصادقة بالبيانات، أدخل اسم المستخدم وكلمة المرور من قائمة Credential List. '
                      . 'للمصادقة بعنوان IP، أضف عنوان IP هذا الخادم في IP Access Control List الخاص بالخط.',
            ],
        ];
    }

    // ── SignalWire ────────────────────────────────────────────────────────────

    private static function signalwire(): array
    {
        return [
            'key'        => 'signalwire',
            'label'      => 'SignalWire',
            'version'    => static::VERSION,
            'docs_url'   => 'https://signalwire.com/docs/platform/voice/sip/sip-credentials',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_port', 'transport', 'register'],
            // sip_host is derived from the Space name the user enters
            'required_fields' => ['space_name', 'username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your SignalWire Space name (shown in your dashboard URL as '
                      . '<space>.signalwire.com). The SIP host will be derived automatically as '
                      . '<space>.sip.signalwire.com. Then enter the SIP Credential username and password '
                      . 'from your SignalWire Space → SIP → SIP Credentials.',
                'ar' => 'أدخل اسم Space في SignalWire (يظهر في عنوان URL للوحة التحكم '
                      . '<space>.signalwire.com). سيُشتق مضيف SIP تلقائياً كـ '
                      . '<space>.sip.signalwire.com. ثم أدخل اسم المستخدم وكلمة المرور من '
                      . 'Space → SIP → SIP Credentials.',
            ],
        ];
    }

    // ── VoiceTel ──────────────────────────────────────────────────────────────

    private static function voicetel(): array
    {
        return [
            'key'        => 'voicetel',
            'label'      => 'VoiceTel',
            'version'    => static::VERSION,
            'docs_url'   => 'https://voicetel.com/support/voice/pbx/fusionpbx/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,   // VoiceTel requires From User ≠ caller ID
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            // For VoiceTel, proxy and realm are derived from the sip_host the user supplies
            'preset_fields'   => ['sip_port', 'transport'],
            'required_fields' => ['sip_host', 'username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter the SIP host assigned in your VoiceTel customer portal. '
                      . 'For Digest authentication: Proxy and Realm are derived from the SIP host, '
                      . 'From User is the SIP account username (kept separate from the outbound caller ID). '
                      . 'Registration is enabled. For IP authentication: registration is disabled; '
                      . 'add this server\'s IP to your VoiceTel account.',
                'ar' => 'أدخل مضيف SIP المعين في بوابة عملاء VoiceTel. '
                      . 'للمصادقة الهضمية (Digest): يُشتق Proxy وRealm من مضيف SIP، '
                      . 'From User هو اسم حساب SIP (مستقل عن معرّف المتصل الصادر). '
                      . 'التسجيل مفعّل. للمصادقة بعنوان IP: التسجيل معطّل؛ '
                      . 'أضف عنوان IP هذا الخادم لحسابك في VoiceTel.',
            ],
        ];
    }

    // ── Generic ───────────────────────────────────────────────────────────────

    private static function generic(): array
    {
        return [
            'key'        => 'generic',
            'label'      => 'Generic SIP',
            'version'    => static::VERSION,
            'docs_url'   => null,
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => [],
            'required_fields' => ['sip_host'],
            'instructions'    => [
                'en' => 'Configure all fields manually. Contact your SIP provider for the correct '
                      . 'host, port, transport, and authentication details.',
                'ar' => 'قم بتكوين جميع الحقول يدوياً. اتصل بمزود SIP للحصول على '
                      . 'المضيف والمنفذ والنقل وتفاصيل المصادقة الصحيحة.',
            ],
        ];
    }
}
