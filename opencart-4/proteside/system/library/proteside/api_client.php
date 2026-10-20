<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Server-side client for the Proteside SDK API:
 *   GET /api/sdk/status  - entitlement (no-store)
 *   GET /api/sdk/config  - remote config (rules, GTM containers, channel)
 *
 * Only used when saving the settings, by "Check status", by the cron and by
 * the deferred revalidation that runs after the storefront response has been
 * sent. Uses cURL when available, otherwise a stream context; the HTTP status
 * code matters because 401 is a definitive answer (invalid key) while
 * 429/5xx are transient.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideApiClient
{
    const API_BASE = 'https://app.proteside.com/api/sdk';
    const PLATFORM = 'opencart';
    const TIMEOUT = 5;

    /** @var string */
    private $version;

    /** @var string */
    private $site;

    /** @var string */
    private $base;

    /**
     * @param string $version extension version
     * @param string $site store URL (sent as `site` and in the User-Agent)
     * @param string|null $base API base (tests only). A PROTESIDE_API_BASE constant defined in
     *                          the OpenCart config.php overrides the default (staging and end-to-end tests).
     */
    public function __construct($version, $site, $base = null)
    {
        $this->version = (string) $version;
        $this->site = (string) $site;
        if (!is_string($base) || $base === '') {
            $base = defined('PROTESIDE_API_BASE') ? (string) constant('PROTESIDE_API_BASE') : self::API_BASE;
        }
        $this->base = rtrim($base, '/');
    }

    /**
     * GET /api/sdk/status, normalised.
     *
     * @param string $key
     *
     * @return array {
     *   ok: bool            definitive answer received (200 or 401)
     *   transient: bool     429 / 5xx / network error: keep previous decision
     *   http: int
     *   status: string      active|grace|suspended|inactive|invalid|connection_error
     *   active: bool
     *   plan: string|null
     *   grace_until: string|null
     *   cdn_base: string|null
     *   domain_mismatch: bool
     *   message: string
     * }
     */
    public function fetchStatus($key)
    {
        $result = array(
            'ok' => false,
            'transient' => true,
            'http' => 0,
            'status' => 'connection_error',
            'active' => false,
            'plan' => null,
            'grace_until' => null,
            'cdn_base' => null,
            'domain_mismatch' => false,
            'message' => '',
        );

        $key = trim((string) $key);
        if (!ProtesideSnippet::isValidKey($key)) {
            $result['ok'] = true;
            $result['transient'] = false;
            $result['status'] = 'invalid';
            $result['message'] = 'malformed key';

            return $result;
        }

        $url = $this->base . '/status?' . http_build_query(array(
            'key' => $key,
            'site' => $this->site,
            'platform' => self::PLATFORM,
            'version' => $this->version,
        ), '', '&', PHP_QUERY_RFC3986);

        $resp = $this->get($url);
        $result['http'] = $resp['code'];
        if ($resp['error'] !== '') {
            $result['message'] = $resp['error'];

            return $result;
        }

        $body = json_decode($resp['body'], true);
        if ($resp['code'] === 200 && is_array($body)) {
            $status = isset($body['status']) ? (string) $body['status'] : '';
            if (!in_array($status, array('active', 'grace', 'suspended', 'inactive'), true)) {
                $status = !empty($body['active']) ? 'active' : 'inactive';
            }
            $result['ok'] = true;
            $result['transient'] = false;
            $result['status'] = $status;
            $result['active'] = !empty($body['active']) && in_array($status, array('active', 'grace'), true);
            $result['plan'] = isset($body['plan']) && is_scalar($body['plan']) ? (string) $body['plan'] : null;
            $result['grace_until'] = isset($body['graceUntil']) && is_scalar($body['graceUntil']) ? (string) $body['graceUntil'] : null;
            $result['cdn_base'] = isset($body['cdnBase']) && is_string($body['cdnBase']) ? $body['cdnBase'] : null;
            $result['domain_mismatch'] = !empty($body['domainMismatch']);

            return $result;
        }

        if ($resp['code'] === 401) {
            $result['ok'] = true;
            $result['transient'] = false;
            $result['status'] = 'invalid';
            $result['active'] = false;
            $result['message'] = 'HTTP 401';

            return $result;
        }

        // 429 / 5xx / unexpected payload: transient.
        $result['message'] = 'HTTP ' . $resp['code'];

        return $result;
    }

    /**
     * GET /api/sdk/config, normalised and filtered.
     *
     * @param string $key
     *
     * @return array {
     *   ok: bool, http: int, message: string,
     *   rules: array, gtm: array, channel: string|null (stable|latest|null)
     * }
     */
    public function fetchConfig($key)
    {
        $result = array('ok' => false, 'http' => 0, 'message' => '', 'rules' => array(), 'gtm' => array(), 'channel' => null);

        $key = trim((string) $key);
        if (!ProtesideSnippet::isValidKey($key)) {
            $result['message'] = 'malformed key';

            return $result;
        }

        $url = $this->base . '/config?' . http_build_query(array('key' => $key), '', '&', PHP_QUERY_RFC3986);
        $resp = $this->get($url);
        $result['http'] = $resp['code'];
        if ($resp['error'] !== '') {
            $result['message'] = $resp['error'];

            return $result;
        }
        $body = json_decode($resp['body'], true);
        if ($resp['code'] !== 200 || !is_array($body)) {
            $result['message'] = 'HTTP ' . $resp['code'];

            return $result;
        }

        $result['ok'] = true;
        $result['rules'] = ProtesideSnippet::filterRules(isset($body['rules']) ? $body['rules'] : array());
        $result['gtm'] = ProtesideSnippet::filterGtm(isset($body['gtmContainers']) ? $body['gtmContainers'] : array());
        if (isset($body['channel']) && in_array($body['channel'], array('stable', 'latest'), true)) {
            $result['channel'] = $body['channel'];
        }

        return $result;
    }

    /**
     * @return string
     */
    public function userAgent()
    {
        return 'Proteside-OpenCart/' . $this->version . '; ' . $this->site;
    }

    /**
     * Minimal HTTPS GET with a 5 s timeout.
     *
     * @param string $url
     *
     * @return array {code: int, body: string, error: string}
     */
    protected function get($url)
    {
        $headers = array('Accept: application/json', 'User-Agent: ' . $this->userAgent());

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ));
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch); // no-op since PHP 8.0, deprecated in 8.5
            }
            if ($body === false || $code === 0) {
                return array('code' => 0, 'body' => '', 'error' => $err !== '' ? $err : 'network error');
            }

            return array('code' => $code, 'body' => (string) $body, 'error' => '');
        }

        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => implode("\r\n", $headers),
            ),
            'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
        ));
        $http_response_header = array();
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return array('code' => 0, 'body' => '', 'error' => 'network error');
        }
        $code = 0;
        $responseHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : $http_response_header;
        if (is_array($responseHeaders) && isset($responseHeaders[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0], $m)) {
            $code = (int) $m[1];
        }
        if ($code === 0) {
            return array('code' => 0, 'body' => '', 'error' => 'no HTTP status');
        }

        return array('code' => $code, 'body' => (string) $body, 'error' => '');
    }
}
