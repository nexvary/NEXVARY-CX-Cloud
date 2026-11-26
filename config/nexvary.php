<?php

return [
    // ── Installation State ────────────────────────────────────────────────
    'installed' => env('NEXVARY_INSTALLED', false),
    'install_secret' => env('NEXVARY_INSTALL_SECRET'),
    'version' => env('NEXVARY_VERSION', '1.0.0'),

    // ── FreeSWITCH ────────────────────────────────────────────────────────
    'freeswitch' => [
        'fusionpbx'    => env('FREESWITCH_FUSIONPBX', false),
        'fusionpbx_admin_url' => env('FUSIONPBX_ADMIN_URL'),
        'host'         => env('FREESWITCH_HOST', '127.0.0.1'),
        'esl_port'     => (int) env('FREESWITCH_ESL_PORT', 8021),
        'esl_password' => env('FREESWITCH_ESL_PASSWORD', 'ClueCon'),
        'sip_host'     => env('FREESWITCH_SIP_HOST', '127.0.0.1'),
        'sip_port'     => (int) env('FREESWITCH_SIP_PORT', 5060),
        'tls_port'     => (int) env('FREESWITCH_TLS_PORT', 5061),
        'conf_dir'     => env('FREESWITCH_CONF_DIR', '/etc/freeswitch'),
        'log_dir'      => env('FREESWITCH_LOG_DIR', '/var/log/freeswitch'),
        // Shared secret for the CDR webhook (mod_xml_cdr → POST /webhooks/freeswitch/cdr)
        'cdr_secret'   => env('NEXVARY_CDR_SECRET'),
    ],

    // ── WebRTC Bridge ─────────────────────────────────────────────────────
    'webrtc' => [
        'bridge_url'  => env('RTC_BRIDGE_URL', 'ws://127.0.0.1:3000'),
        'bridge_token'=> env('RTC_BRIDGE_TOKEN'),
        'stun'        => env('STUN_SERVER', 'stun:stun.l.google.com:19302'),
        'turn'        => env('TURN_SERVER'),
        'turn_user'   => env('TURN_USER'),
        'turn_cred'   => env('TURN_CREDENTIAL'),
    ],

    // ── Telnyx ────────────────────────────────────────────────────────────
    'telnyx' => [
        'api_key'              => env('TELNYX_API_KEY'),
        'public_key'           => env('TELNYX_PUBLIC_KEY'),
        'webhook_public_key'   => env('TELNYX_WEBHOOK_PUBLIC_KEY'),
        'messaging_profile_id' => env('TELNYX_MESSAGING_PROFILE_ID'),
        'webhook_secret'       => env('TELNYX_WEBHOOK_SECRET'),
        'sip_trunk_id'         => env('TELNYX_SIP_TRUNK_ID'),
    ],

    // ── Voice AI ──────────────────────────────────────────────────────────
    'ai' => [
        'enabled'       => env('VOICE_AI_ENABLED', false),
        'provider'      => env('AI_PROVIDER', 'openai'),
        'openai_key'    => env('OPENAI_API_KEY'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
        'tts_engine'    => env('TTS_ENGINE', 'openai'),
        'stt_engine'    => env('STT_ENGINE', 'openai'),
    ],

    // ── Security ──────────────────────────────────────────────────────────
    'security' => [
        'mfa_enabled'          => env('MFA_ENABLED', true),
        'max_login_attempts'   => env('SESSION_MAX_ATTEMPTS', 5),
        'admin_network_allow'  => env('ADMIN_NETWORK_ALLOW'),
        'toll_fraud_protection'=> env('TOLL_FRAUD_PROTECTION', true),
        'max_concurrent_calls' => (int) env('MAX_CONCURRENT_CALLS', 50),
        'max_international_calls' => (int) env('MAX_INTERNATIONAL_CALLS', 10),
    ],

    // ── Recordings ────────────────────────────────────────────────────────
    'recordings' => [
        'storage'        => env('RECORDING_STORAGE', 'local'),
        'retention_days' => (int) env('RECORDING_RETENTION_DAYS', 90),
    ],
];
