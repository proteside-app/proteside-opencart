<?php
/**
 * Proteside checkout protection for OpenCart 3.0.x - storefront.
 *
 * injectHeader: event on catalog/view/common/header/after. Inserts the two
 *   Proteside tags right after the opening head tag of the rendered header,
 *   so they are the first scripts of the page.
 * cron:  token-protected JSON endpoint that refreshes the status of every store.
 * probe: token-protected JSON endpoint used by the admin "Test the installation".
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ControllerExtensionModuleProteside extends Controller {
	/**
	 * The extension has no layout block; placing it in a layout prints nothing.
	 *
	 * @return string
	 */
	public function index() {
		return '';
	}

	/**
	 * @param string $route
	 * @param array  $args
	 * @param string $output rendered header
	 */
	public function injectHeader(&$route, &$args, &$output) {
		try {
			if (!is_string($output) || $output === '') {
				return;
			}

			$service = $this->service();

			$store_id = (int)$this->config->get('config_store_id');
			$settings = ProtesideSettings::normalize($this->configSettings());

			if ($settings['key'] === '') {
				return;
			}

			// The state map is a store 0 setting, already loaded into the configuration.
			$map = $this->config->get(ProtesideStorage::STATE_KEY);
			$state = ProtesideEntitlement::forKey(is_array($map) && isset($map[$store_id]) ? $map[$store_id] : null, $settings['key']);

			$now = time();
			$request = ProtesidePageMatcher::describeRequest($this->request->get, $_SERVER);

			if ($service->shouldInject($settings, $state, $request, $now)) {
				$output = ProtesideSnippet::injectAfterHead($output, $service->render($settings, $state));
			}

			if ($settings['enabled'] && !ProtesideService::$forceInject) {
				$service->scheduleDeferredRefresh($store_id, $settings, $state, $this->siteUrl(), $now);
			}
		} catch (Exception $e) {
			// Never break the storefront.
		} catch (Throwable $e) {
		}
	}

	/**
	 * GET index.php?route=extension/module/proteside/cron&token=...
	 */
	public function cron() {
		$service = $this->service();

		if (!$this->validToken($service)) {
			$this->json(403, array('ok' => false, 'error' => 'invalid token'));

			return;
		}

		$sites = array(0 => $this->defaultStoreUrl());

		foreach ($service->storage()->stores() as $store) {
			$sites[$store['store_id']] = $store['url'];
		}

		$force = !empty($this->request->get['force']);

		$this->json(200, array('ok' => true, 'stores' => $service->refreshAll($sites, !$force)));
	}

	/**
	 * GET index.php?route=extension/module/proteside/probe&token=...
	 * Renders the header of this store with the snippet forced on and reports
	 * where it ended up.
	 */
	public function probe() {
		$service = $this->service();

		if (!$this->validToken($service)) {
			$this->json(403, array('ok' => false, 'error' => 'invalid token'));

			return;
		}

		$settings = ProtesideSettings::normalize($this->configSettings());

		if (!ProtesideSnippet::isValidKey($settings['key'])) {
			$this->json(200, array('ok' => false, 'error' => 'no_key'));

			return;
		}

		ProtesideService::$forceInject = true;

		$header = $this->load->controller('common/header');

		ProtesideService::$forceInject = false;

		$this->json(200, array_merge(array('ok' => true, 'store_id' => (int)$this->config->get('config_store_id')), ProtesideSnippet::inspect($header)));
	}

	/**
	 * @return ProtesideService
	 */
	private function service() {
		require_once(DIR_SYSTEM . 'library/proteside/proteside.php');

		return new ProtesideService(new ProtesideStorage($this->db, DB_PREFIX), DIR_SYSTEM . 'library/proteside');
	}

	/**
	 * Settings of the current store, as loaded by OpenCart (store 0 values
	 * overridden by the store's own).
	 *
	 * @return array
	 */
	private function configSettings() {
		$raw = array();

		foreach (array_keys(ProtesideSettings::defaults()) as $key) {
			$value = $this->config->get($key);

			if ($value !== null) {
				$raw[$key] = $value;
			}
		}

		return $raw;
	}

	/**
	 * @return string URL of the current store, sent as `site`
	 */
	private function siteUrl() {
		$url = $this->config->get('config_ssl') ? $this->config->get('config_ssl') : $this->config->get('config_url');

		return $url ? $url : $this->defaultStoreUrl();
	}

	/**
	 * @return string
	 */
	private function defaultStoreUrl() {
		return defined('HTTPS_SERVER') && HTTPS_SERVER ? HTTPS_SERVER : HTTP_SERVER;
	}

	/**
	 * @param ProtesideService $service
	 *
	 * @return bool
	 */
	private function validToken(ProtesideService $service) {
		$given = isset($this->request->get['token']) ? (string)$this->request->get['token'] : '';

		return $given !== '' && hash_equals($service->storage()->cronToken(), $given);
	}

	/**
	 * @param int   $code
	 * @param array $payload
	 */
	private function json($code, array $payload) {
		http_response_code($code);

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Robots-Tag: noindex');
		$this->response->setOutput(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
	}
}
