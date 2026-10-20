<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Merchant settings (`module_proteside_*` keys of the `setting` table),
 * normalised. Pure.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideSettings
{
    const PREFIX = 'module_proteside_';

    /**
     * Stored defaults, written on install and used for missing keys.
     *
     * @return array key => value, with the module_proteside_ prefix
     */
    public static function defaults()
    {
        return array(
            'module_proteside_status' => '1',
            'module_proteside_sdk_key' => '',
            'module_proteside_pages' => ProtesidePageMatcher::defaultPages(),
            'module_proteside_custom_urls' => '',
            'module_proteside_channel' => 'auto',
            'module_proteside_calibration' => '',
            'module_proteside_inline_rules' => '1',
            'module_proteside_interval' => '12',
            'module_proteside_deferred' => '1',
        );
    }

    /**
     * @param array $raw prefixed keys (from the configuration or the form)
     *
     * @return array {
     *   enabled: bool, key: string, pages: array, custom: array,
     *   channel: auto|stable|latest, calibration: int (0 = none),
     *   inline_rules: bool, interval: 6|12|24, deferred: bool
     * }
     */
    public static function normalize(array $raw)
    {
        $raw = array_merge(self::defaults(), $raw);

        $channel = (string) $raw['module_proteside_channel'];
        if (!in_array($channel, array('auto', 'stable', 'latest'), true)) {
            $channel = 'auto';
        }
        $interval = (int) $raw['module_proteside_interval'];
        if (!in_array($interval, array(6, 12, 24), true)) {
            $interval = 12;
        }

        return array(
            'enabled' => (bool) $raw['module_proteside_status'],
            'key' => trim((string) $raw['module_proteside_sdk_key']),
            'pages' => ProtesidePageMatcher::normalizePages($raw['module_proteside_pages']),
            'custom' => ProtesidePageMatcher::normalizeCustom($raw['module_proteside_custom_urls']),
            'channel' => $channel,
            'calibration' => self::calibration($raw['module_proteside_calibration']),
            'inline_rules' => (bool) $raw['module_proteside_inline_rules'],
            'interval' => $interval,
            'deferred' => (bool) $raw['module_proteside_deferred'],
        );
    }

    /**
     * Calibration in seconds: 10..120, anything else means "none" (0).
     *
     * @param mixed $value
     *
     * @return int
     */
    public static function calibration($value)
    {
        $value = trim((string) $value);
        if ($value === '' || !ctype_digit($value)) {
            return 0;
        }
        $n = (int) $value;

        return $n >= 10 && $n <= 120 ? $n : 0;
    }

    /**
     * Validate a submitted form. Returns field => error code
     * (`sdk_key`, `calibration`); empty when valid. An empty key is allowed
     * (protection simply stays off).
     *
     * @param array $post
     *
     * @return array
     */
    public static function validate(array $post)
    {
        $errors = array();
        $key = isset($post['module_proteside_sdk_key']) ? trim((string) $post['module_proteside_sdk_key']) : '';
        if ($key !== '' && !ProtesideSnippet::isValidKey($key)) {
            $errors['sdk_key'] = 'invalid';
        }
        $cal = isset($post['module_proteside_calibration']) ? trim((string) $post['module_proteside_calibration']) : '';
        if ($cal !== '' && self::calibration($cal) === 0) {
            $errors['calibration'] = 'range';
        }

        return $errors;
    }

    /**
     * Clean a submitted form into the stored shape (prefixed keys only,
     * every checkbox present).
     *
     * @param array $post
     *
     * @return array
     */
    public static function sanitize(array $post)
    {
        $pages = array();
        $postedPages = isset($post['module_proteside_pages']) && is_array($post['module_proteside_pages'])
            ? $post['module_proteside_pages'] : array();
        foreach (array_keys(ProtesidePageMatcher::defaultPages()) as $group) {
            $pages[$group] = !empty($postedPages[$group]) ? 1 : 0;
        }
        $n = self::normalize(array(
            'module_proteside_status' => !empty($post['module_proteside_status']) ? '1' : '0',
            'module_proteside_sdk_key' => isset($post['module_proteside_sdk_key']) ? $post['module_proteside_sdk_key'] : '',
            'module_proteside_pages' => $pages,
            'module_proteside_custom_urls' => isset($post['module_proteside_custom_urls']) ? $post['module_proteside_custom_urls'] : '',
            'module_proteside_channel' => isset($post['module_proteside_channel']) ? $post['module_proteside_channel'] : 'auto',
            'module_proteside_calibration' => isset($post['module_proteside_calibration']) ? $post['module_proteside_calibration'] : '',
            'module_proteside_inline_rules' => !empty($post['module_proteside_inline_rules']) ? '1' : '0',
            'module_proteside_interval' => isset($post['module_proteside_interval']) ? $post['module_proteside_interval'] : '12',
            'module_proteside_deferred' => !empty($post['module_proteside_deferred']) ? '1' : '0',
        ));

        return array(
            'module_proteside_status' => $n['enabled'] ? '1' : '0',
            'module_proteside_sdk_key' => ProtesideSnippet::isValidKey($n['key']) ? $n['key'] : '',
            'module_proteside_pages' => $pages,
            'module_proteside_custom_urls' => implode("\n", $n['custom']),
            'module_proteside_channel' => $n['channel'],
            'module_proteside_calibration' => $n['calibration'] ? (string) $n['calibration'] : '',
            'module_proteside_inline_rules' => $n['inline_rules'] ? '1' : '0',
            'module_proteside_interval' => (string) $n['interval'],
            'module_proteside_deferred' => $n['deferred'] ? '1' : '0',
        );
    }
}
