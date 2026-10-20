<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Builds the data of the admin status card and diagnostics, shared by the
 * OpenCart 3.0 and 4.x admin controllers. Pure: texts come from a translator
 * callable (the OpenCart language object).
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideAdminView
{
    const DASHBOARD_URL = 'https://app.proteside.com';
    const PLANS_URL = 'https://app.proteside.com/plans';
    const DOCS_URL = 'https://docs.proteside.com';

    /**
     * @param ProtesideService $service
     * @param array $settings ProtesideSettings::normalize()
     * @param array $state
     * @param int $now
     * @param callable $t function (string $key): string
     *
     * @return array template variables (prefixed with ps_)
     */
    public static function build(ProtesideService $service, array $settings, array $state, $now, $t)
    {
        $display = ProtesideEntitlement::displayStatus($state, $now);
        $hasKey = ProtesideSnippet::isValidKey($settings['key']);
        $entitled = ProtesideEntitlement::shouldInject($state, $now);
        $active = $settings['enabled'] && $hasKey && $entitled && $service->snippet()->hasBootstrapper();

        $tones = array(
            'active' => 'success',
            'grace' => 'warning',
            'suspended' => 'danger',
            'inactive' => 'danger',
            'invalid' => 'danger',
            'connection_error' => 'warning',
            'unknown' => 'neutral',
        );

        $notices = array();
        if (!$service->snippet()->hasBootstrapper()) {
            $notices[] = array('tone' => 'danger', 'text' => call_user_func($t, 'error_library'));
        }
        if (!$hasKey) {
            $notices[] = array('tone' => 'info', 'text' => call_user_func($t, 'text_notice_no_key'));
        } elseif (!$settings['enabled']) {
            $notices[] = array('tone' => 'warning', 'text' => call_user_func($t, 'text_notice_disabled'));
        }
        if ($hasKey) {
            if ($state['status'] === 'grace' && $entitled) {
                $notices[] = array('tone' => 'warning', 'text' => call_user_func($t, 'text_notice_grace'));
            }
            if ($state['status'] === 'invalid') {
                $notices[] = array('tone' => 'danger', 'text' => call_user_func($t, 'text_notice_invalid'));
            }
            if (in_array($state['status'], array('suspended', 'inactive'), true)) {
                $notices[] = array('tone' => 'danger', 'text' => call_user_func($t, 'text_notice_suspended'));
            }
            if (!empty($state['last_error'])) {
                $reason = $state['message'] !== '' ? $state['message'] : 'HTTP ' . (int) $state['http_code'];
                $notices[] = array('tone' => 'warning', 'text' => sprintf(call_user_func($t, 'text_notice_connection'), $reason));
            }
            if (!empty($state['domain_mismatch'])) {
                $notices[] = array('tone' => 'info', 'text' => call_user_func($t, 'text_domain_mismatch'));
            }
        }

        $inlineJs = $service->inlineJs($settings, $state);
        $rules = $settings['inline_rules'] ? count($state['inline_rules']) : 0;
        $gtm = $settings['inline_rules'] ? count($state['inline_gtm']) : 0;

        return array(
            'ps_status_code' => $hasKey ? $display : 'unknown',
            'ps_status_text' => call_user_func($t, 'status_' . ($hasKey ? $display : 'unknown')),
            'ps_status_tone' => $hasKey ? $tones[$display] : 'neutral',
            'ps_plan_text' => !empty($state['plan']) ? sprintf(call_user_func($t, 'text_plan'), $state['plan']) : '',
            'ps_checked_text' => !empty($state['checked_at'])
                ? sprintf(call_user_func($t, 'text_checked_ago'), self::ago((int) $now - (int) $state['checked_at'], $t))
                : '',
            'ps_decision_active' => $active,
            'ps_decision_text' => call_user_func($t, $active ? 'diag_decision_active' : 'diag_decision_inactive'),
            'ps_notices' => $notices,
            'ps_extension_version' => ProtesideService::VERSION,
            'ps_bootstrapper_version' => $service->bootstrapperVersion(),
            'ps_channel_in_use' => ProtesideEntitlement::effectiveChannel($settings['channel'], $state),
            'ps_inline_counts' => $rules . ' / ' . $gtm,
            'ps_csp_hash' => "'" . ProtesideSnippet::cspHash($inlineJs) . "'",
            'ps_dashboard_url' => self::DASHBOARD_URL,
            'ps_plans_url' => self::PLANS_URL,
            'ps_docs_url' => self::DOCS_URL,
        );
    }

    /**
     * Human duration ("less than a minute", "5 min", "3 h", "2 d").
     *
     * @param int $seconds
     * @param callable $t
     *
     * @return string
     */
    public static function ago($seconds, $t)
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds < 60) {
            return call_user_func($t, 'text_less_minute');
        }
        if ($seconds < 3600) {
            return sprintf(call_user_func($t, 'text_minutes'), (int) floor($seconds / 60));
        }
        if ($seconds < 172800) {
            return sprintf(call_user_func($t, 'text_hours'), (int) floor($seconds / 3600));
        }

        return sprintf(call_user_func($t, 'text_days'), (int) floor($seconds / 86400));
    }

    /**
     * Interpret the JSON returned by the storefront probe.
     *
     * @param array|null $probe decoded JSON, or null when the request failed
     * @param string $error transport error
     * @param callable $t
     *
     * @return array ['tone' => success|warning|danger, 'text' => string]
     */
    public static function probeMessage($probe, $error, $t)
    {
        if (!is_array($probe)) {
            return array('tone' => 'danger', 'text' => sprintf(call_user_func($t, 'probe_error'), $error !== '' ? $error : 'invalid response'));
        }
        if (isset($probe['error']) && $probe['error'] === 'no_key') {
            return array('tone' => 'warning', 'text' => call_user_func($t, 'probe_no_key'));
        }
        if (empty($probe['ok'])) {
            return array('tone' => 'danger', 'text' => sprintf(call_user_func($t, 'probe_error'), isset($probe['error']) ? (string) $probe['error'] : 'error'));
        }
        if (empty($probe['head_found'])) {
            return array('tone' => 'danger', 'text' => call_user_func($t, 'probe_no_head'));
        }
        if (empty($probe['injected'])) {
            return array('tone' => 'danger', 'text' => call_user_func($t, 'probe_not_injected'));
        }
        if (empty($probe['first_in_head'])) {
            return array('tone' => 'warning', 'text' => sprintf(call_user_func($t, 'probe_not_first'), (int) $probe['scripts_before']));
        }

        return array('tone' => 'success', 'text' => call_user_func($t, 'probe_ok'));
    }

    /**
     * Server-side GET of the storefront probe (5 s timeout, follows
     * redirects so http -> https and www redirects work).
     *
     * @param string $url
     *
     * @return array ['json' => array|null, 'error' => string]
     */
    public static function fetchProbe($url)
    {
        $headers = array('Accept: application/json', 'User-Agent: Proteside-OpenCart/' . ProtesideService::VERSION . ' probe');
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
            ));
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch); // no-op since PHP 8.0, deprecated in 8.5
            }
            if ($body === false || $code === 0) {
                return array('json' => null, 'error' => $err !== '' ? $err : 'network error');
            }
        } else {
            $ctx = stream_context_create(array('http' => array('timeout' => 10, 'ignore_errors' => true, 'header' => implode("\r\n", $headers))));
            $body = @file_get_contents($url, false, $ctx);
            $code = $body === false ? 0 : 200;
            if ($body === false) {
                return array('json' => null, 'error' => 'network error');
            }
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            return array('json' => null, 'error' => 'HTTP ' . $code);
        }

        return array('json' => $json, 'error' => '');
    }
}
