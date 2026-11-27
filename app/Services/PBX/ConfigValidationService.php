<?php
namespace App\Services\PBX;

/**
 * Validates FreeSWITCH configuration data before writing or activating.
 * Returns ['valid' => bool, 'errors' => string[]].
 */
class ConfigValidationService
{
    public static function validateSipProfile(array $data): array
    {
        $errors = [];

        if (isset($data['sip_port']) && ($data['sip_port'] < 1024 || $data['sip_port'] > 65535)) {
            $errors[] = 'sip_port must be between 1024 and 65535.';
        }
        if (isset($data['tls_port']) && ($data['tls_port'] < 1024 || $data['tls_port'] > 65535)) {
            $errors[] = 'tls_port must be between 1024 and 65535.';
        }
        if (!empty($data['sip_ip']) && !self::isValidIpOrAuto($data['sip_ip'])) {
            $errors[] = 'sip_ip must be a valid IP address, hostname or "auto".';
        }
        if (!empty($data['ext_sip_ip']) && !self::isValidIpOrAuto($data['ext_sip_ip'])) {
            $errors[] = 'ext_sip_ip must be a valid IP address, hostname or "auto".';
        }
        if (!empty($data['codecs'])) {
            $allowed = ['PCMU', 'PCMA', 'G722', 'G729', 'opus', 'G723', 'G726', 'iLBC', 'speex', 'SILK', 'GSM'];
            foreach ($data['codecs'] as $codec) {
                if (!in_array(strtoupper($codec), array_map('strtoupper', $allowed))) {
                    $errors[] = "Unknown codec: {$codec}.";
                }
            }
        }
        if (isset($data['srtp_mode']) && !in_array($data['srtp_mode'], ['disabled', 'optional', 'mandatory'])) {
            $errors[] = 'srtp_mode must be disabled, optional or mandatory.';
        }
        if (isset($data['rtp_timeout']) && ($data['rtp_timeout'] < 30 || $data['rtp_timeout'] > 7200)) {
            $errors[] = 'rtp_timeout must be between 30 and 7200 seconds.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public static function validateDialplanRule(array $data): array
    {
        $errors = [];

        if (empty($data['conditions']) || !is_array($data['conditions'])) {
            $errors[] = 'At least one condition is required.';
        } else {
            foreach ($data['conditions'] as $i => $cond) {
                if (empty($cond['field'])) {
                    $errors[] = "Condition #{$i}: field is required.";
                }
                if (isset($cond['expression']) && @preg_match('/^(?:' . $cond['expression'] . ')$/', '') === false) {
                    $errors[] = "Condition #{$i}: expression is not a valid regular expression.";
                }
            }
        }

        if (empty($data['actions']) || !is_array($data['actions'])) {
            $errors[] = 'At least one action is required.';
        } else {
            foreach ($data['actions'] as $i => $action) {
                if (empty($action['application'])) {
                    $errors[] = "Action #{$i}: application is required.";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public static function validateXml(string $xml): array
    {
        $dom    = new \DOMDocument();
        $errors = [];
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xml)) {
            foreach (libxml_get_errors() as $e) {
                $errors[] = trim($e->message);
            }
            libxml_clear_errors();
        }
        libxml_use_internal_errors(false);
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    private static function isValidIpOrAuto(string $value): bool
    {
        return $value === 'auto'
            || filter_var($value, FILTER_VALIDATE_IP) !== false
            || filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
