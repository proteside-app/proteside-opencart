<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Entitlement state and the injection decision. Every method is pure, so the
 * decision table can be unit-tested; persistence lives in ProtesideStorage.
 *
 * Decision table (only HTTP 200 and 401 change the decision):
 *
 *   last definitive answer          API now       injects?
 *   active / grace                  OK            yes (grace shows a billing warning)
 *   suspended / inactive / invalid  OK            no
 *   never validated                 unavailable   no (fail-closed)
 *   active / grace, success <= 48 h unavailable   yes (tolerance)
 *   active / grace, success  > 48 h unavailable   no
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideEntitlement
{
    const TOLERANCE = 172800; // 48 h
    const LOCK_TTL = 120;     // a refresh in progress blocks others for 2 min

    /**
     * @return array
     */
    public static function defaults()
    {
        return array(
            'status' => 'unknown',      // last definitive answer: unknown|active|grace|suspended|inactive|invalid
            'active' => false,
            'plan' => null,
            'grace_until' => null,
            'cdn_base' => null,
            'domain_mismatch' => false,
            'checked_at' => 0,          // last attempt (any outcome)
            'last_success_at' => 0,     // last definitive answer (200/401)
            'last_error' => false,      // last attempt was transient (429/5xx/network)
            'http_code' => 0,
            'message' => '',
            'channel' => null,          // channel from /api/sdk/config
            'inline_rules' => array(),
            'inline_gtm' => array(),
            'config_checked_at' => 0,
            'key_hash' => '',           // sha1 of the key this state belongs to
            'lock_until' => 0,
        );
    }

    /**
     * Merge a stored state (array or JSON string) with the defaults.
     *
     * @param mixed $raw
     *
     * @return array
     */
    public static function decode($raw)
    {
        $state = self::defaults();
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        if (is_array($raw)) {
            foreach ($state as $k => $default) {
                if (array_key_exists($k, $raw)) {
                    $state[$k] = $raw[$k];
                }
            }
        }
        if (!is_array($state['inline_rules'])) {
            $state['inline_rules'] = array();
        }
        if (!is_array($state['inline_gtm'])) {
            $state['inline_gtm'] = array();
        }

        return $state;
    }

    /**
     * Fingerprint of a key, to drop a state that belongs to another key.
     *
     * @param string $key
     *
     * @return string
     */
    public static function keyHash($key)
    {
        return sha1((string) $key);
    }

    /**
     * The state for a key: the stored one if it belongs to that key,
     * otherwise a fresh (never validated) state.
     *
     * @param mixed $raw
     * @param string $key
     *
     * @return array
     */
    public static function forKey($raw, $key)
    {
        $state = self::decode($raw);
        if ($state['key_hash'] !== self::keyHash($key)) {
            $state = self::defaults();
            $state['key_hash'] = self::keyHash($key);
        }

        return $state;
    }

    /**
     * Apply a normalised /api/sdk/status result. Only 200 and 401 change the
     * decision; transient failures (429/5xx/network) keep the previous one.
     *
     * @param array $state
     * @param array $result ProtesideApiClient::fetchStatus()
     * @param int $now
     *
     * @return array
     */
    public static function applyStatus(array $state, array $result, $now)
    {
        $state['checked_at'] = (int) $now;
        $state['http_code'] = isset($result['http']) ? (int) $result['http'] : 0;
        $state['message'] = isset($result['message']) ? (string) $result['message'] : '';

        if (!empty($result['transient'])) {
            $state['last_error'] = true;

            return $state;
        }

        $state['last_error'] = false;
        $state['last_success_at'] = (int) $now;
        $status = isset($result['status']) ? (string) $result['status'] : 'invalid';
        if (!in_array($status, array('active', 'grace', 'suspended', 'inactive', 'invalid'), true)) {
            $status = 'invalid';
        }
        $state['status'] = $status;
        $state['active'] = in_array($status, array('active', 'grace'), true) && !empty($result['active']);
        $state['plan'] = isset($result['plan']) ? $result['plan'] : null;
        $state['grace_until'] = isset($result['grace_until']) ? $result['grace_until'] : null;
        $state['domain_mismatch'] = !empty($result['domain_mismatch']);
        if (!empty($result['cdn_base']) && is_string($result['cdn_base'])) {
            $state['cdn_base'] = $result['cdn_base'];
        }
        if (!$state['active']) {
            $state['inline_rules'] = array();
            $state['inline_gtm'] = array();
        }

        return $state;
    }

    /**
     * Apply a normalised /api/sdk/config result. A failed fetch keeps the
     * previously cached rules.
     *
     * @param array $state
     * @param array $result ProtesideApiClient::fetchConfig()
     * @param int $now
     *
     * @return array
     */
    public static function applyConfig(array $state, array $result, $now)
    {
        if (empty($result['ok'])) {
            return $state;
        }
        $state['config_checked_at'] = (int) $now;
        $state['inline_rules'] = isset($result['rules']) && is_array($result['rules']) ? array_values($result['rules']) : array();
        $state['inline_gtm'] = isset($result['gtm']) && is_array($result['gtm']) ? array_values($result['gtm']) : array();
        $state['channel'] = isset($result['channel']) && in_array($result['channel'], array('stable', 'latest'), true)
            ? $result['channel'] : null;

        return $state;
    }

    /**
     * The injection decision (see the table at the top of this file).
     *
     * @param array $state
     * @param int $now
     *
     * @return bool
     */
    public static function shouldInject(array $state, $now)
    {
        $status = isset($state['status']) ? $state['status'] : 'unknown';
        if (!in_array($status, array('active', 'grace'), true) || empty($state['active'])) {
            return false; // suspended / inactive / invalid / never validated
        }
        if (empty($state['last_error'])) {
            return true; // last answer was definitive and positive
        }
        $last = isset($state['last_success_at']) ? (int) $state['last_success_at'] : 0;

        return $last > 0 && ((int) $now - $last) <= self::TOLERANCE;
    }

    /**
     * What the admin status badge should say.
     *
     * @param array $state
     * @param int $now
     *
     * @return string active|grace|suspended|inactive|invalid|connection_error|unknown
     */
    public static function displayStatus(array $state, $now)
    {
        $status = isset($state['status']) ? $state['status'] : 'unknown';
        if (!empty($state['last_error'])) {
            if ($status === 'unknown') {
                return 'connection_error';
            }
            if (in_array($status, array('active', 'grace'), true) && !self::shouldInject($state, $now)) {
                return 'connection_error';
            }
        }
        if (!in_array($status, array('active', 'grace', 'suspended', 'inactive', 'invalid'), true)) {
            return 'unknown';
        }

        return $status;
    }

    /**
     * Effective shield.js channel.
     *
     * @param string $setting auto|stable|latest
     * @param array $state
     *
     * @return string stable|latest
     */
    public static function effectiveChannel($setting, array $state)
    {
        if ($setting === 'stable' || $setting === 'latest') {
            return $setting;
        }

        return isset($state['channel']) && $state['channel'] === 'latest' ? 'latest' : 'stable';
    }

    /**
     * Is a revalidation due?
     *
     * @param array $state
     * @param int $intervalHours
     * @param int $now
     *
     * @return bool
     */
    public static function isDue(array $state, $intervalHours, $now)
    {
        $interval = max(1, (int) $intervalHours) * 3600;
        $checked = isset($state['checked_at']) ? (int) $state['checked_at'] : 0;

        return ((int) $now - $checked) >= $interval;
    }

    /**
     * Is another request refreshing this state right now?
     *
     * @param array $state
     * @param int $now
     *
     * @return bool
     */
    public static function isLocked(array $state, $now)
    {
        return isset($state['lock_until']) && (int) $state['lock_until'] > (int) $now;
    }
}
