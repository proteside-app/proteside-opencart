<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Orchestration shared by the OpenCart 3.0 and 4.x controllers: status
 * refresh, injection decision, snippet rendering and the deferred
 * revalidation that runs after the storefront response has been sent.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideService
{
    const VERSION = '1.0.0';

    /** @var bool set by the probe route: inject regardless of page and entitlement */
    public static $forceInject = false;

    /** @var bool a deferred refresh was already scheduled in this request */
    private static $deferredScheduled = false;

    /** @var ProtesideStorage */
    private $storage;

    /** @var string directory of this library */
    private $libDir;

    /** @var ProtesideSnippet|null */
    private $snippet;

    /** @var ProtesideApiClient|null client used instead of the default one (tests) */
    private $client;

    /**
     * @param ProtesideStorage $storage
     * @param string $libDir
     */
    public function __construct(ProtesideStorage $storage, $libDir)
    {
        $this->storage = $storage;
        $this->libDir = rtrim((string) $libDir, '/\\');
    }

    /**
     * @param ProtesideApiClient|null $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @return ProtesideStorage
     */
    public function storage()
    {
        return $this->storage;
    }

    /**
     * @return ProtesideSnippet
     */
    public function snippet()
    {
        if ($this->snippet === null) {
            $this->snippet = ProtesideSnippet::fromFile($this->libDir . '/bootstrapper/bootstrapper.min.js');
        }

        return $this->snippet;
    }

    /**
     * @return string version of the bundled bootstrapper
     */
    public function bootstrapperVersion()
    {
        $v = @file_get_contents($this->libDir . '/bootstrapper/VERSION');

        return is_string($v) ? trim($v) : '';
    }

    /**
     * Call /api/sdk/status (and /api/sdk/config when the key is entitled),
     * store and return the new state.
     *
     * @param int $storeId
     * @param array $settings ProtesideSettings::normalize()
     * @param string $siteUrl store URL sent as `site`
     * @param int|null $now
     * @param ProtesideApiClient|null $client
     *
     * @return array state
     */
    public function refresh($storeId, array $settings, $siteUrl, $now = null, $client = null)
    {
        $now = $now === null ? time() : (int) $now;
        $key = $settings['key'];
        $state = $this->storage->loadState($storeId, $key);
        $client = $client ?: ($this->client ?: new ProtesideApiClient(self::VERSION, $siteUrl));

        $state = ProtesideEntitlement::applyStatus($state, $client->fetchStatus($key), $now);
        if (ProtesideEntitlement::shouldInject($state, $now) && ProtesideSnippet::isValidKey($key)) {
            $state = ProtesideEntitlement::applyConfig($state, $client->fetchConfig($key), $now);
        }
        $state['lock_until'] = 0;
        $this->storage->saveState($storeId, $state);

        return $state;
    }

    /**
     * Refresh every store whose settings have a key (cron).
     *
     * @param array $sites store_id => site URL (store 0 included)
     * @param bool $onlyDue skip stores checked within their interval
     * @param int|null $now
     *
     * @return array store_id => summary
     */
    public function refreshAll(array $sites, $onlyDue = false, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $out = array();
        foreach ($sites as $storeId => $site) {
            $settings = ProtesideSettings::normalize($this->storage->effectiveSettings($storeId));
            if ($settings['key'] === '') {
                continue;
            }
            $state = $this->storage->loadState($storeId, $settings['key']);
            if ($onlyDue && !ProtesideEntitlement::isDue($state, $settings['interval'], $now)) {
                $out[$storeId] = self::summary($state, $now, 'skipped');
                continue;
            }
            $state = $this->refresh($storeId, $settings, $site, $now);
            $out[$storeId] = self::summary($state, $now, 'checked');
        }

        return $out;
    }

    /**
     * Should the snippet be injected on this request?
     *
     * @param array $settings
     * @param array $state
     * @param array $request ProtesidePageMatcher::describeRequest()
     * @param int $now
     *
     * @return bool
     */
    public function shouldInject(array $settings, array $state, array $request, $now)
    {
        if (!ProtesideSnippet::isValidKey($settings['key']) || !$this->snippet()->hasBootstrapper()) {
            return false;
        }
        if (self::$forceInject) {
            return true;
        }
        if (!$settings['enabled'] || !ProtesideEntitlement::shouldInject($state, $now)) {
            return false;
        }

        return ProtesidePageMatcher::matches($settings['pages'], $settings['custom'], $request);
    }

    /**
     * The snippet for the given settings and state.
     *
     * @param array $settings
     * @param array $state
     *
     * @return string
     */
    public function render(array $settings, array $state)
    {
        $inline = $settings['inline_rules'];

        return $this->snippet()->render($settings['key'], array(
            'channel' => ProtesideEntitlement::effectiveChannel($settings['channel'], $state),
            'cdn_base' => $state['cdn_base'],
            'calibration' => $settings['calibration'],
            'rules' => $inline ? $state['inline_rules'] : array(),
            'gtm' => $inline ? $state['inline_gtm'] : array(),
        ));
    }

    /**
     * Inline script body that render() emits (for the CSP hash).
     *
     * @param array $settings
     * @param array $state
     *
     * @return string
     */
    public function inlineJs(array $settings, array $state)
    {
        $inline = $settings['inline_rules'];

        return $this->snippet()->inlineJs(
            $inline ? $state['inline_rules'] : array(),
            $inline ? $state['inline_gtm'] : array()
        );
    }

    /**
     * Schedule a status refresh after the response is sent, when the
     * interval has elapsed. Nothing is called on the render path: with
     * PHP-FPM (or LiteSpeed) the connection is closed before the request to
     * Proteside starts.
     *
     * @param int $storeId
     * @param array $settings
     * @param array $state
     * @param string $siteUrl
     * @param int $now
     *
     * @return bool scheduled
     */
    public function scheduleDeferredRefresh($storeId, array $settings, array $state, $siteUrl, $now)
    {
        if (self::$deferredScheduled || !$settings['deferred'] || !ProtesideSnippet::isValidKey($settings['key'])) {
            return false;
        }
        if (!ProtesideEntitlement::isDue($state, $settings['interval'], $now) || ProtesideEntitlement::isLocked($state, $now)) {
            return false;
        }
        self::$deferredScheduled = true;
        $self = $this;
        register_shutdown_function(function () use ($self, $storeId, $settings, $siteUrl) {
            $self->runDeferredRefresh($storeId, $settings, $siteUrl);
        });

        return true;
    }

    /**
     * Body of the deferred refresh (public for the shutdown closure).
     *
     * @param int $storeId
     * @param array $settings
     * @param string $siteUrl
     */
    public function runDeferredRefresh($storeId, array $settings, $siteUrl)
    {
        self::finishResponse();
        try {
            $now = time();
            // Re-read: another request may have refreshed or taken the lock meanwhile.
            $state = $this->storage->loadState($storeId, $settings['key']);
            if (!ProtesideEntitlement::isDue($state, $settings['interval'], $now) || ProtesideEntitlement::isLocked($state, $now)) {
                return;
            }
            $state['lock_until'] = $now + ProtesideEntitlement::LOCK_TTL;
            $this->storage->saveState($storeId, $state);
            $this->refresh($storeId, $settings, $siteUrl, $now);
        } catch (Exception $e) {
            // Never break the storefront; the next request retries after the lock expires.
        } catch (Throwable $e) {
        }
    }

    /**
     * Send the response to the visitor before slow work.
     */
    public static function finishResponse()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();
        }
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
    }

    /**
     * Compact view of a state (cron JSON, admin).
     *
     * @param array $state
     * @param int $now
     * @param string $action
     *
     * @return array
     */
    public static function summary(array $state, $now, $action = '')
    {
        return array(
            'action' => $action,
            'status' => $state['status'],
            'display_status' => ProtesideEntitlement::displayStatus($state, $now),
            'entitled' => ProtesideEntitlement::shouldInject($state, $now),
            'plan' => $state['plan'],
            'checked_at' => (int) $state['checked_at'],
            'last_success_at' => (int) $state['last_success_at'],
            'http_code' => (int) $state['http_code'],
            'inline_rules' => count($state['inline_rules']),
            'inline_gtm' => count($state['inline_gtm']),
            'channel' => $state['channel'],
        );
    }
}
