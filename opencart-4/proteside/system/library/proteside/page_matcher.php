<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Decides whether the current storefront request belongs to one of the
 * enabled page groups. Pages are detected through the OpenCart route
 * (`route` request parameter, also set by SEO URLs), never through URLs,
 * except for the merchant-supplied custom path patterns.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesidePageMatcher
{
    /**
     * Default page selection: checkout and order confirmation on.
     *
     * @return array
     */
    public static function defaultPages()
    {
        return array(
            'checkout' => true,
            'order_confirmation' => true,
            'cart' => false,
            'shop' => false,
            'account' => false,
            'login' => false,
            'all_store' => false,
        );
    }

    /**
     * Normalise a stored page selection (missing groups take the default).
     *
     * @param mixed $pages
     *
     * @return array
     */
    public static function normalizePages($pages)
    {
        $out = self::defaultPages();
        if (!is_array($pages)) {
            return $out;
        }
        foreach (array_keys($out) as $k) {
            if (array_key_exists($k, $pages)) {
                $out[$k] = (bool) $pages[$k];
            }
        }

        return $out;
    }

    /**
     * Custom patterns: newline-separated string or array; trimmed, without
     * empties or duplicates.
     *
     * @param mixed $custom
     *
     * @return array list of strings
     */
    public static function normalizeCustom($custom)
    {
        if (is_string($custom)) {
            $custom = preg_split('/[\r\n]+/', $custom);
        }
        if (!is_array($custom)) {
            return array();
        }
        $out = array();
        foreach ($custom as $line) {
            $p = trim((string) $line);
            if ($p !== '' && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Page route without the method part: `checkout/checkout.save` (4.0.2+),
     * `checkout/checkout|save` (4.0.0/4.0.1) and `checkout/checkout` all
     * become `checkout/checkout`. An empty route is the home page.
     *
     * @param string $route
     *
     * @return string
     */
    public static function normalizeRoute($route)
    {
        $route = strtolower(trim((string) $route));
        $route = preg_replace('/[^a-z0-9_\/.|]/', '', $route);
        $cut = strcspn($route, '.|');
        $route = trim(substr($route, 0, $cut), '/');

        return $route === '' ? 'common/home' : $route;
    }

    /**
     * Which page group a route belongs to.
     *
     * @param string $route
     *
     * @return string checkout|order_confirmation|cart|shop|account|login|other
     */
    public static function groupOf($route)
    {
        $r = self::normalizeRoute($route);
        $seg = explode('/', $r);

        if ($r === 'checkout/success' || $r === 'checkout/failure') {
            return 'order_confirmation';
        }
        if ($r === 'checkout/cart' || strpos($r, 'checkout/cart/') === 0) {
            return 'cart';
        }
        if ($seg[0] === 'checkout') {
            return 'checkout'; // checkout/checkout and one-page checkouts installed under checkout/
        }
        // Payment extension pages: 3.0 extension/payment/<code>, 4.x extension/<vendor>/payment/<code>
        if ($seg[0] === 'extension' && ((isset($seg[1]) && $seg[1] === 'payment') || (isset($seg[2]) && $seg[2] === 'payment'))) {
            return 'checkout';
        }
        // Third-party one-page checkouts (journal3/checkout, extension/quickcheckout/checkout, ...)
        foreach ($seg as $s) {
            if (strpos($s, 'checkout') !== false) {
                return 'checkout';
            }
        }
        if ($seg[0] === 'account') {
            $page = isset($seg[1]) ? $seg[1] : '';

            return in_array($page, array('login', 'forgotten', 'reset'), true) ? 'login' : 'account';
        }
        if ($seg[0] === 'affiliate') {
            return 'account';
        }
        if ($r === 'common/home' || $seg[0] === 'product') {
            return 'shop';
        }

        return 'other';
    }

    /**
     * Pure decision.
     *
     * @param array $pages normalised page selection
     * @param array $custom normalised custom patterns
     * @param array $request route (string), path (string), uri (string)
     *
     * @return bool
     */
    public static function matches(array $pages, array $custom, array $request)
    {
        $pages = self::normalizePages($pages);
        if ($pages['all_store']) {
            return true;
        }
        $group = self::groupOf(isset($request['route']) ? $request['route'] : '');
        if ($group !== 'other' && !empty($pages[$group])) {
            return true;
        }
        if (!empty($custom)) {
            $path = isset($request['path']) ? (string) $request['path'] : '';
            $uri = isset($request['uri']) ? (string) $request['uri'] : $path;
            if (self::matchesCustom($custom, $path, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match the request against patterns (`*` wildcard anchored on the
     * whole path or URI; patterns without `*` are substring matches).
     *
     * @param array $patterns
     * @param string $path
     * @param string $uri
     *
     * @return bool
     */
    public static function matchesCustom(array $patterns, $path, $uri = '')
    {
        if ($path === '' && $uri === '') {
            return false;
        }
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }
            if (strpos($pattern, '*') !== false) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                if (preg_match($regex, $path) || ($uri !== '' && preg_match($regex, $uri))) {
                    return true;
                }
            } elseif (stripos($path, $pattern) !== false || ($uri !== '' && stripos($uri, $pattern) !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Request description from the OpenCart request object.
     *
     * @param array $get $this->request->get
     * @param array $server $this->request->server
     *
     * @return array route, path, uri
     */
    public static function describeRequest(array $get, array $server)
    {
        $uri = isset($server['REQUEST_URI']) && is_string($server['REQUEST_URI']) ? $server['REQUEST_URI'] : '';
        $path = $uri !== '' ? parse_url($uri, PHP_URL_PATH) : '';

        return array(
            'route' => isset($get['route']) && is_string($get['route']) ? $get['route'] : '',
            'path' => is_string($path) ? $path : '',
            'uri' => $uri,
        );
    }
}
