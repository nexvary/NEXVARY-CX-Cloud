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
 *
 * All presets are based on official provider documentation. They represent
 * documented configuration starting points, not live-tested integrations.
 * Verify with your provider before production deployment.
 */
class SipProviderPresets
{
    public const VERSION = '2026-11';

    public static function all(): array
    {
        return [
            // CX13: 25 documented provider presets
            'telnyx'             => static::telnyx(),
            'twilio'             => static::twilio(),
            'vonage'             => static::vonage(),
            'plivo'              => static::plivo(),
            'bandwidth'          => static::bandwidth(),
            'signalwire'         => static::signalwire(),
            'flowroute'          => static::flowroute(),
            'voip_ms'            => static::voipMs(),
            'zadarma'            => static::zadarma(),
            'didww'              => static::didww(),
            'callcentric'        => static::callcentric(),
            'anveo'              => static::anveo(),
            'bulkvs'             => static::bulkvs(),
            'skyetel'            => static::skyetel(),
            'sip_us'             => static::sipUs(),
            'siptrunk'           => static::siptrunk(),
            'voip_innovations'   => static::voipInnovations(),
            'commio'             => static::commio(),
            'voip_unlimited'     => static::voipUnlimited(),
            'gamma'              => static::gamma(),
            'sipgate'            => static::sipgate(),
            'localphone'         => static::localphone(),
            'voicetel'           => static::voicetel(),
            'tata_communications'=> static::tataCommunications(),
            'sinch'              => static::sinch(),
            // Generic fallback (not counted in the 25 documented presets)
            'generic'            => static::generic(),
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

    /** Return only the 25 documented provider presets (excludes 'generic'). */
    public static function documented(): array
    {
        $all = static::all();
        unset($all['generic']);
        return $all;
    }

    // ── Telnyx ────────────────────────────────────────────────────────────────

    private static function telnyx(): array
    {
        return [
            'key'        => 'telnyx',
            'label'      => 'Telnyx',
            'version'    => static::VERSION,
            'docs_url'   => 'https://developers.telnyx.com/docs/voice/sip-trunking',
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
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'realm', 'register'],
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

    // ── Vonage (Nexmo) ────────────────────────────────────────────────────────

    private static function vonage(): array
    {
        return [
            'key'        => 'vonage',
            'label'      => 'Vonage (Nexmo)',
            'version'    => static::VERSION,
            'docs_url'   => 'https://developer.vonage.com/en/voice/sip',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.nexmo.com',
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Create a SIP endpoint in your Vonage API Dashboard and enter the '
                      . 'username and password. The SIP host sip.nexmo.com is preset. '
                      . 'TLS transport is recommended.',
                'ar' => 'أنشئ SIP endpoint في لوحة Vonage API وأدخل اسم المستخدم وكلمة المرور. '
                      . 'المضيف sip.nexmo.com معيّن مسبقاً. يُنصح باستخدام TLS.',
            ],
        ];
    }

    // ── Plivo ─────────────────────────────────────────────────────────────────

    private static function plivo(): array
    {
        return [
            'key'        => 'plivo',
            'label'      => 'Plivo',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.plivo.com/docs/voice/concepts/sip-trunking/',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'phone.plivo.com',
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Create a SIP endpoint in your Plivo console and enter the endpoint '
                      . 'username and password. The SIP host phone.plivo.com is preset.',
                'ar' => 'أنشئ SIP endpoint في لوحة Plivo وأدخل اسم المستخدم وكلمة المرور للـ endpoint. '
                      . 'المضيف phone.plivo.com معيّن مسبقاً.',
            ],
        ];
    }

    // ── Bandwidth ─────────────────────────────────────────────────────────────

    private static function bandwidth(): array
    {
        return [
            'key'        => 'bandwidth',
            'label'      => 'Bandwidth',
            'version'    => static::VERSION,
            'docs_url'   => 'https://dev.bandwidth.com/docs/voice/quickStart/',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5006,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            // Bandwidth provides an account-specific SIP URI
            'preset_fields'   => ['sip_port', 'transport', 'register'],
            'required_fields' => ['sip_host', 'username', 'sip_password'],
            'instructions'    => [
                'en' => 'Bandwidth assigns an account-specific SIP URI for your location. '
                      . 'Enter the Origination URI from your Bandwidth dashboard '
                      . '(Numbers → Location/SIP Peer → Origination Settings) as the SIP host. '
                      . 'Enter your SIP peer username and password.',
                'ar' => 'تُخصص Bandwidth عنوان SIP URI خاصاً بحسابك. '
                      . 'أدخل Origination URI من لوحة Bandwidth '
                      . '(Numbers → Location/SIP Peer → Origination Settings) كمضيف SIP. '
                      . 'أدخل اسم المستخدم وكلمة المرور لـ SIP peer.',
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

    // ── Flowroute ─────────────────────────────────────────────────────────────

    private static function flowroute(): array
    {
        return [
            'key'        => 'flowroute',
            'label'      => 'Flowroute',
            'version'    => static::VERSION,
            'docs_url'   => 'https://support.flowroute.com/documentation/getting-started/sip-connection-setup/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.flowroute.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.flowroute.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Flowroute tech prefix as the username and your Flowroute password. '
                      . 'The SIP host sip.flowroute.com is preset. For IP authentication, '
                      . 'add this server\'s IP to your Flowroute account IP list.',
                'ar' => 'أدخل tech prefix في Flowroute كاسم مستخدم وكلمة المرور. '
                      . 'المضيف sip.flowroute.com معيّن مسبقاً. للمصادقة بعنوان IP، '
                      . 'أضف عنوان IP هذا الخادم لقائمة IP في حسابك في Flowroute.',
            ],
        ];
    }

    // ── VoIP.ms ───────────────────────────────────────────────────────────────

    private static function voipMs(): array
    {
        return [
            'key'        => 'voip_ms',
            'label'      => 'VoIP.ms',
            'version'    => static::VERSION,
            'docs_url'   => 'https://wiki.voip.ms/article/IP_PBX_Integration',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            // VoIP.ms uses per-account server selection; user must supply sip_host
            'preset_fields'   => ['sip_port', 'transport', 'register'],
            'required_fields' => ['sip_host', 'username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter the VoIP.ms server for your sub-account (e.g. chicago1.voip.ms). '
                      . 'Your server is listed in your VoIP.ms portal under your sub-account settings. '
                      . 'Enter your VoIP.ms account username and sub-account password.',
                'ar' => 'أدخل خادم VoIP.ms لحسابك الفرعي (مثلاً: chicago1.voip.ms). '
                      . 'خادمك مدرج في بوابة VoIP.ms تحت إعدادات الحساب الفرعي. '
                      . 'أدخل اسم مستخدم حساب VoIP.ms وكلمة مرور الحساب الفرعي.',
            ],
        ];
    }

    // ── Zadarma ───────────────────────────────────────────────────────────────

    private static function zadarma(): array
    {
        return [
            'key'        => 'zadarma',
            'label'      => 'Zadarma',
            'version'    => static::VERSION,
            'docs_url'   => 'https://zadarma.com/en/support/faq/voip-connection/',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.zadarma.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Zadarma SIP account number as the username and your SIP password. '
                      . 'Find both in your Zadarma account under Settings → PBX Setup → SIP.',
                'ar' => 'أدخل رقم حساب SIP في Zadarma كاسم مستخدم وكلمة مرور SIP. '
                      . 'ستجد كليهما في حساب Zadarma تحت الإعدادات → PBX Setup → SIP.',
            ],
        ];
    }

    // ── DIDWW ─────────────────────────────────────────────────────────────────

    private static function didww(): array
    {
        return [
            'key'        => 'didww',
            'label'      => 'DIDWW',
            'version'    => static::VERSION,
            'docs_url'   => 'https://support.didww.com/documentation/sip-trunks',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.didww.com',
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.didww.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Create a SIP trunk in your DIDWW customer portal and enter the '
                      . 'trunk username and password. The SIP host sip.didww.com is preset. '
                      . 'For IP authentication, add this server\'s IP to your trunk\'s IP list in the portal.',
                'ar' => 'أنشئ SIP trunk في بوابة DIDWW وأدخل اسم المستخدم وكلمة المرور. '
                      . 'المضيف sip.didww.com معيّن مسبقاً. للمصادقة بعنوان IP، '
                      . 'أضف عنوان IP هذا الخادم لقائمة IP للخط في البوابة.',
            ],
        ];
    }

    // ── Callcentric ───────────────────────────────────────────────────────────

    private static function callcentric(): array
    {
        return [
            'key'        => 'callcentric',
            'label'      => 'Callcentric',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.callcentric.com/features/sip',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'phone.callcentric.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Callcentric account number (1777XXXXXXX format) as the username '
                      . 'and your Callcentric SIP password. The SIP host phone.callcentric.com is preset.',
                'ar' => 'أدخل رقم حساب Callcentric (بصيغة 1777XXXXXXX) كاسم مستخدم '
                      . 'وكلمة مرور SIP الخاصة بـ Callcentric. المضيف phone.callcentric.com معيّن مسبقاً.',
            ],
        ];
    }

    // ── Anveo ─────────────────────────────────────────────────────────────────

    private static function anveo(): array
    {
        return [
            'key'        => 'anveo',
            'label'      => 'Anveo',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.anveo.com/self-service/how-to-connect-your-sip-pbx',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip1.anveo.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Anveo SIP username and password from your Anveo account '
                      . '(Settings → SIP Credentials). The primary SIP host sip1.anveo.com is preset; '
                      . 'use sip2.anveo.com as a backup gateway if needed.',
                'ar' => 'أدخل اسم مستخدم SIP وكلمة المرور من حساب Anveo '
                      . '(Settings → SIP Credentials). المضيف الرئيسي sip1.anveo.com معيّن مسبقاً؛ '
                      . 'استخدم sip2.anveo.com كبوابة احتياطية عند الحاجة.',
            ],
        ];
    }

    // ── BulkVS ────────────────────────────────────────────────────────────────

    private static function bulkvs(): array
    {
        return [
            'key'        => 'bulkvs',
            'label'      => 'BulkVS',
            'version'    => static::VERSION,
            'docs_url'   => 'https://portal.bulkvs.com/api/v1.0#tag/Trunks',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.bulkvs.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.bulkvs.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Create a SIP trunk in your BulkVS portal and enter the trunk '
                      . 'username and password. For IP authentication, '
                      . 'add this server\'s IP to the trunk\'s allowed IP list.',
                'ar' => 'أنشئ SIP trunk في بوابة BulkVS وأدخل اسم المستخدم وكلمة المرور. '
                      . 'للمصادقة بعنوان IP، أضف عنوان IP هذا الخادم لقائمة عناوين IP المسموح بها.',
            ],
        ];
    }

    // ── Skyetel ───────────────────────────────────────────────────────────────

    private static function skyetel(): array
    {
        return [
            'key'        => 'skyetel',
            'label'      => 'Skyetel',
            'version'    => static::VERSION,
            'docs_url'   => 'https://support.skyetel.com/hc/en-us/articles/360043501973',
            'auth_modes' => ['ip_acl'],
            'defaults'   => [
                'ip_acl' => [
                    'sip_host'          => 'sip.skyetel.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => [],
            'instructions'    => [
                'en' => 'Skyetel uses IP authentication only. Add this server\'s IP address '
                      . 'to your Skyetel account under Endpoints → IP ACLs. No registration username '
                      . 'or password is used. Configure your DIDs to route to this server\'s IP.',
                'ar' => 'تستخدم Skyetel المصادقة بعنوان IP فقط. أضف عنوان IP هذا الخادم '
                      . 'لحسابك في Skyetel تحت Endpoints → IP ACLs. لا يُستخدم اسم مستخدم أو كلمة مرور. '
                      . 'قم بتوجيه أرقام DID لعنوان IP هذا الخادم.',
            ],
        ];
    }

    // ── SIP.US ────────────────────────────────────────────────────────────────

    private static function sipUs(): array
    {
        return [
            'key'        => 'sip_us',
            'label'      => 'SIP.US',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.sip.us/knowledge-base/asterisk-and-open-source/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.sip.us',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.sip.us',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter the SIP channel username and password from your SIP.US portal '
                      . '(Channels → your channel). For IP authentication, '
                      . 'add this server\'s IP to the channel\'s allowed IP list.',
                'ar' => 'أدخل اسم مستخدم قناة SIP وكلمة المرور من بوابة SIP.US '
                      . '(Channels → قناتك). للمصادقة بعنوان IP، '
                      . 'أضف عنوان IP هذا الخادم لقائمة IPs المسموح بها للقناة.',
            ],
        ];
    }

    // ── SIPTRUNK ──────────────────────────────────────────────────────────────

    private static function siptrunk(): array
    {
        return [
            'key'        => 'siptrunk',
            'label'      => 'SIPTRUNK',
            'version'    => static::VERSION,
            'docs_url'   => 'https://siptrunk.com/kb',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.siptrunk.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.siptrunk.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your SIPTRUNK username and password from your account portal. '
                      . 'For IP authentication, add this server\'s IP to your SIPTRUNK account.',
                'ar' => 'أدخل اسم مستخدم SIPTRUNK وكلمة المرور من بوابة حسابك. '
                      . 'للمصادقة بعنوان IP، أضف عنوان IP هذا الخادم لحسابك في SIPTRUNK.',
            ],
        ];
    }

    // ── VoIP Innovations ──────────────────────────────────────────────────────

    private static function voipInnovations(): array
    {
        return [
            'key'        => 'voip_innovations',
            'label'      => 'VoIP Innovations',
            'version'    => static::VERSION,
            'docs_url'   => 'https://backoffice.voipinnovations.com/docs',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.voipinnovations.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.voipinnovations.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your VoIP Innovations login and password. '
                      . 'For IP authentication, add this server\'s IP in your VoIP Innovations account.',
                'ar' => 'أدخل اسم مستخدم VoIP Innovations وكلمة المرور. '
                      . 'للمصادقة بعنوان IP، أضف عنوان IP هذا الخادم لحسابك في VoIP Innovations.',
            ],
        ];
    }

    // ── Commio ────────────────────────────────────────────────────────────────

    private static function commio(): array
    {
        return [
            'key'        => 'commio',
            'label'      => 'Commio',
            'version'    => static::VERSION,
            'docs_url'   => 'https://commio.com/sip-trunking/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'trunking.commio.io',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'trunking.commio.io',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Commio trunk username and password from your Commio portal. '
                      . 'For IP authentication, add this server\'s IP to your trunk settings.',
                'ar' => 'أدخل اسم مستخدم trunk في Commio وكلمة المرور من بوابة Commio. '
                      . 'للمصادقة بعنوان IP، أضف عنوان IP هذا الخادم لإعدادات الخط.',
            ],
        ];
    }

    // ── VoIP Unlimited ────────────────────────────────────────────────────────

    private static function voipUnlimited(): array
    {
        return [
            'key'        => 'voip_unlimited',
            'label'      => 'VoIP Unlimited',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.voipunlimited.co.uk/support',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.voipunlimited.co.uk',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your VoIP Unlimited SIP account username and password. '
                      . 'Contact VoIP Unlimited support to obtain your SIP trunk credentials.',
                'ar' => 'أدخل اسم مستخدم حساب SIP في VoIP Unlimited وكلمة المرور. '
                      . 'تواصل مع دعم VoIP Unlimited للحصول على بيانات اعتماد SIP trunk.',
            ],
        ];
    }

    // ── Gamma ─────────────────────────────────────────────────────────────────

    private static function gamma(): array
    {
        return [
            'key'        => 'gamma',
            'label'      => 'Gamma',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.gamma.co.uk/partners/product-catalogue/sip-trunks/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
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
            // Gamma provides a per-account SIP proxy; user must supply
            'preset_fields'   => ['sip_port', 'transport'],
            'required_fields' => ['sip_host', 'username', 'sip_password'],
            'instructions'    => [
                'en' => 'Gamma assigns a SIP proxy address per account. Enter the proxy address '
                      . 'and your SIP trunk username and password from your Gamma portal or Welcome Pack. '
                      . 'For IP authentication, provide your server\'s IP to your Gamma account manager.',
                'ar' => 'تُخصص Gamma عنوان SIP proxy لكل حساب. أدخل عنوان الوكيل '
                      . 'واسم مستخدم SIP trunk وكلمة المرور من بوابة Gamma أو Welcome Pack. '
                      . 'للمصادقة بعنوان IP، زود مدير حسابك في Gamma بعنوان IP هذا الخادم.',
            ],
        ];
    }

    // ── Sipgate ───────────────────────────────────────────────────────────────

    private static function sipgate(): array
    {
        return [
            'key'        => 'sipgate',
            'label'      => 'Sipgate',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.sipgate.co.uk/rest-api-docs',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sipgate.de',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Sipgate SIP username and password from your Sipgate account '
                      . '(Sipgate basic/TRUNKS → SIP credentials). For UK accounts use '
                      . 'sipgate.co.uk as the SIP host.',
                'ar' => 'أدخل اسم مستخدم SIP وكلمة المرور من حساب Sipgate '
                      . '(Sipgate basic/TRUNKS → SIP credentials). '
                      . 'للحسابات في المملكة المتحدة استخدم sipgate.co.uk كمضيف SIP.',
            ],
        ];
    }

    // ── Localphone ────────────────────────────────────────────────────────────

    private static function localphone(): array
    {
        return [
            'key'        => 'localphone',
            'label'      => 'Localphone',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.localphone.com/sip',
            'auth_modes' => ['credential'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.localphone.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
                    'auth_mode'         => 'credential',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port', 'transport', 'register'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Localphone account number as the username and your SIP password. '
                      . 'Find your SIP credentials in your Localphone account under SIP Settings.',
                'ar' => 'أدخل رقم حساب Localphone كاسم مستخدم وكلمة مرور SIP. '
                      . 'ستجد بيانات اعتماد SIP في حساب Localphone تحت إعدادات SIP.',
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
            'docs_url'   => 'https://voicetel.com/support/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
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

    // ── Tata Communications ───────────────────────────────────────────────────

    private static function tataCommunications(): array
    {
        return [
            'key'        => 'tata_communications',
            'label'      => 'Tata Communications',
            'version'    => static::VERSION,
            'docs_url'   => 'https://www.tatacommunications.com/solutions/network/voip-services/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => true,
                    'caller_id_in_from' => false,
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
            // Enterprise service; SIP endpoint is provisioned per account
            'preset_fields'   => ['sip_port', 'transport'],
            'required_fields' => ['sip_host', 'username', 'sip_password'],
            'instructions'    => [
                'en' => 'Tata Communications provides an enterprise SIP trunking service. '
                      . 'Your account manager will supply the SIP proxy address, '
                      . 'trunk username and password. Enter these in the fields above.',
                'ar' => 'تُقدم Tata Communications خدمة SIP trunking للمؤسسات. '
                      . 'سيزودك مدير الحساب بعنوان وكيل SIP واسم المستخدم وكلمة المرور. '
                      . 'أدخل هذه البيانات في الحقول أعلاه.',
            ],
        ];
    }

    // ── Sinch ─────────────────────────────────────────────────────────────────

    private static function sinch(): array
    {
        return [
            'key'        => 'sinch',
            'label'      => 'Sinch',
            'version'    => static::VERSION,
            'docs_url'   => 'https://developers.sinch.com/docs/voice/getting-started/sip-trunking/',
            'auth_modes' => ['credential', 'ip_acl'],
            'defaults'   => [
                'credential' => [
                    'sip_host'          => 'sip.sinch.com',
                    'sip_port'          => 5060,
                    'transport'         => 'tls',
                    'register'          => true,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'credential',
                ],
                'ip_acl' => [
                    'sip_host'          => 'sip.sinch.com',
                    'sip_port'          => 5060,
                    'transport'         => 'udp',
                    'register'          => false,
                    'caller_id_in_from' => true,
                    'auth_mode'         => 'ip_acl',
                ],
            ],
            'preset_fields'   => ['sip_host', 'sip_port'],
            'required_fields' => ['username', 'sip_password'],
            'instructions'    => [
                'en' => 'Enter your Sinch SIP username and password from your Sinch dashboard '
                      . '(Voice → SIP Trunking → Credentials). TLS is recommended. '
                      . 'For IP authentication, add this server\'s IP to your Sinch trunk settings.',
                'ar' => 'أدخل اسم مستخدم SIP وكلمة المرور من لوحة Sinch '
                      . '(Voice → SIP Trunking → Credentials). يُنصح باستخدام TLS. '
                      . 'للمصادقة بعنوان IP، أضف عنوان IP هذا الخادم لإعدادات خط Sinch.',
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
