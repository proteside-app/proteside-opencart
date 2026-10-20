<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Pure composition of the two Proteside tags (inline bootstrapper + async
 * shield.js). No OpenCart dependency, so it can be unit-tested with plain PHP.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideSnippet
{
    const CDN_BASE_DEFAULT = 'https://cdn.proteside.com/v1';
    const API_BASE = 'https://app.proteside.com/api/sdk';
    const USE_STRICT = '"use strict";';
    const START_MARKER = '<!-- Proteside Start -->';
    const END_MARKER = '<!-- Proteside End -->';
    const KEY_PATTERN = '/^pk_(live|test)_[0-9a-f]{32}$/D';
    const GTM_PATTERN = '/^GTM-[A-Z0-9]{4,10}$/D';

    /** @var string Raw content of bootstrapper.min.js */
    private $bootstrapper;

    /**
     * @param string $bootstrapperJs content of the bundled bootstrapper.min.js
     */
    public function __construct($bootstrapperJs)
    {
        // Kept byte-for-byte (including the trailing newline) so that, without
        // inline rules, the CSP hash equals the hash of the published file.
        $this->bootstrapper = ltrim((string) $bootstrapperJs, "\xEF\xBB\xBF \t\r\n");
    }

    /**
     * @param string $path path of bootstrapper.min.js
     *
     * @return ProtesideSnippet
     */
    public static function fromFile($path)
    {
        $js = is_file($path) ? file_get_contents($path) : '';

        return new self(is_string($js) ? $js : '');
    }

    /**
     * Is the bundled bootstrapper present and well-formed?
     *
     * @return bool
     */
    public function hasBootstrapper()
    {
        return strpos($this->bootstrapper, self::USE_STRICT) === 0 && strlen($this->bootstrapper) > 1000;
    }

    /**
     * Is the SDK key well-formed? (pk_live_/pk_test_ + 32 hex chars)
     *
     * @param string $key
     *
     * @return bool
     */
    public static function isValidKey($key)
    {
        return is_string($key) && (bool) preg_match(self::KEY_PATTERN, $key);
    }

    /**
     * Keep only the rules the bootstrapper understands.
     *
     * @param mixed $rules decoded `rules` from /api/sdk/config
     *
     * @return array list of ['type' => ..., 'target' => ..., 'value' => ...]
     */
    public static function filterRules($rules)
    {
        $out = array();
        if (!is_array($rules)) {
            return $out;
        }
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $type = isset($rule['type']) ? $rule['type'] : null;
            $target = isset($rule['target']) ? $rule['target'] : null;
            $value = isset($rule['value']) ? $rule['value'] : null;
            if (!in_array($type, array('block', 'allow'), true)) {
                continue;
            }
            if (!in_array($target, array('domain', 'src', 'hash'), true)) {
                continue;
            }
            if (!is_string($value) || $value === '') {
                continue;
            }
            $out[] = array('type' => $type, 'target' => $target, 'value' => $value);
        }

        return $out;
    }

    /**
     * Keep only well-formed GTM container IDs.
     *
     * @param mixed $containers decoded `gtmContainers` from /api/sdk/config
     *
     * @return array list of strings
     */
    public static function filterGtm($containers)
    {
        $out = array();
        if (!is_array($containers)) {
            return $out;
        }
        foreach ($containers as $id) {
            if (is_string($id) && preg_match(self::GTM_PATTERN, $id) && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * JavaScript of the inline tag:
     * `"use strict";window.__PROTE_INLINE__={...};<rest of bootstrapper>`.
     * The assignment goes inside the same script, after the directive, and is
     * omitted entirely when both lists are empty.
     *
     * @param array $rules already filtered rules
     * @param array $gtm already filtered GTM IDs
     *
     * @return string
     */
    public function inlineJs(array $rules = array(), array $gtm = array())
    {
        $rest = $this->bootstrapper;
        if (strpos($rest, self::USE_STRICT) === 0) {
            $rest = substr($rest, strlen(self::USE_STRICT));
        }

        $prefix = self::USE_STRICT;
        if (!empty($rules) || !empty($gtm)) {
            $payload = array('rules' => array_values($rules), 'gtm' => array_values($gtm));
            // JSON_HEX_TAG turns < and > into \u003C / \u003E, so the inline
            // script can never contain "</script>".
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            if (is_string($json) && $json !== '') {
                $prefix .= 'window.__PROTE_INLINE__=' . $json . ';';
            }
        }

        return $prefix . $rest;
    }

    /**
     * The inline bootstrapper tag, with the attributes that keep cache and
     * minification extensions from deferring or moving it.
     *
     * @param array $rules
     * @param array $gtm
     *
     * @return string
     */
    public function inlineTag(array $rules = array(), array $gtm = array())
    {
        return '<script data-cfasync="false" data-no-optimize="1" data-nowprocket data-pagespeed-no-defer>'
            . $this->inlineJs($rules, $gtm)
            . '</script>';
    }

    /**
     * shield.js URL for a channel (stable: /v1/shield.js, latest: /v1/latest/shield.js).
     *
     * @param string $channel `stable` or `latest`
     * @param string|null $cdnBase `cdnBase` from /api/sdk/status, or null for the default
     *
     * @return string
     */
    public static function shieldSrc($channel, $cdnBase = null)
    {
        $base = is_string($cdnBase) ? rtrim(trim($cdnBase), '/') : '';
        if ($base === '' || !preg_match('#^https://[a-z0-9.-]+\.proteside\.com(/[A-Za-z0-9._/-]*)?$#', $base)) {
            $base = self::CDN_BASE_DEFAULT;
        }

        return $base . ($channel === 'latest' ? '/latest/shield.js' : '/shield.js');
    }

    /**
     * The async shield.js tag. Only data-key, data-api and (optionally)
     * data-calibration exist.
     *
     * @param string $key
     * @param string $channel
     * @param string|null $cdnBase
     * @param int $calibration 0 to omit, otherwise 10..120
     *
     * @return string
     */
    public static function shieldTag($key, $channel = 'stable', $cdnBase = null, $calibration = 0)
    {
        $attrs = 'async src="' . self::attr(self::shieldSrc($channel, $cdnBase)) . '"'
            . ' data-key="' . self::attr($key) . '"'
            . ' data-api="' . self::attr(self::API_BASE) . '"';
        $calibration = (int) $calibration;
        if ($calibration >= 10 && $calibration <= 120) {
            $attrs .= ' data-calibration="' . $calibration . '"';
        }

        return '<script ' . $attrs . '></script>';
    }

    /**
     * Full snippet: markers + inline bootstrapper + async shield tag.
     *
     * @param string $key
     * @param array $options keys: channel, cdn_base, calibration, rules, gtm
     *
     * @return string
     */
    public function render($key, array $options = array())
    {
        $channel = isset($options['channel']) && $options['channel'] === 'latest' ? 'latest' : 'stable';
        $cdnBase = isset($options['cdn_base']) ? $options['cdn_base'] : null;
        $calibration = isset($options['calibration']) ? (int) $options['calibration'] : 0;
        $rules = isset($options['rules']) && is_array($options['rules']) ? $options['rules'] : array();
        $gtm = isset($options['gtm']) && is_array($options['gtm']) ? $options['gtm'] : array();

        return "\n" . self::START_MARKER . "\n"
            . $this->inlineTag($rules, $gtm) . "\n"
            . self::shieldTag($key, $channel, $cdnBase, $calibration) . "\n"
            . self::END_MARKER . "\n";
    }

    /**
     * Insert the snippet right after the opening <head ...> tag. A
     * `<meta charset>` (or http-equiv Content-Type) that comes immediately
     * after <head> stays first, because browsers only look for it in the first
     * 1024 bytes; it is not a script, so the installation order is unaffected.
     * Returns the HTML unchanged when there is no <head> or when the snippet
     * is already present.
     *
     * @param string $html
     * @param string $snippet
     *
     * @return string
     */
    public static function injectAfterHead($html, $snippet)
    {
        if (!is_string($html) || $html === '' || strpos($html, self::START_MARKER) !== false) {
            return $html;
        }
        if (!preg_match('/<head(?:\s[^>]*)?>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $pos = $m[0][1] + strlen($m[0][0]);

        $charset = '/\G\s*<meta\s+(?:charset\s*=|http-equiv\s*=\s*["\']?content-type)[^>]*>/i';
        if (preg_match($charset, $html, $c, 0, $pos)) {
            $pos += strlen($c[0]);
        }

        return substr($html, 0, $pos) . $snippet . substr($html, $pos);
    }

    /**
     * Where the snippet sits in a rendered document.
     *
     * @param string $html
     *
     * @return array {
     *   head_found: bool, injected: bool, first_in_head: bool,
     *   scripts_before: int  <script> tags between <head> and the snippet
     * }
     */
    public static function inspect($html)
    {
        $out = array('head_found' => false, 'injected' => false, 'first_in_head' => false, 'scripts_before' => 0);
        if (!is_string($html) || $html === '') {
            return $out;
        }
        if (!preg_match('/<head(?:\s[^>]*)?>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }
        $out['head_found'] = true;
        $headEnd = $m[0][1] + strlen($m[0][0]);
        $marker = strpos($html, self::START_MARKER);
        if ($marker === false) {
            return $out;
        }
        $out['injected'] = true;
        $before = $marker >= $headEnd ? substr($html, $headEnd, $marker - $headEnd) : substr($html, 0, $marker);
        $out['scripts_before'] = preg_match_all('/<script\b/i', $before, $unused);
        $out['first_in_head'] = $marker >= $headEnd && $out['scripts_before'] === 0;

        return $out;
    }

    /**
     * sha256 CSP source of an inline script body.
     *
     * @param string $js
     *
     * @return string e.g. sha256-...
     */
    public static function cspHash($js)
    {
        return 'sha256-' . base64_encode(hash('sha256', (string) $js, true));
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private static function attr($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
