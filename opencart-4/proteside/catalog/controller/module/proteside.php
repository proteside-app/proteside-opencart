<?php
namespace Opencart\Catalog\Controller\Extension\Proteside\Module;
/**
 * Proteside checkout protection for OpenCart 4.0.2+ / 4.1 - storefront.
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
class Proteside extends \Opencart\System\Engine\Controller {
	/**
	 * The extension has no layout block; placing it in a layout prints nothing.
	 */
	public function index(): string {
		return '';
	}

	/**
	 * Defaults keep a direct request to this route harmless.
	 */
	public function injectHeader(string &$route = '', array &$args = [], string &$output = ''): void {
		try {
			if ($output === '') {
				return;
			}

			$service = $this->service();

			$store_id = (int)$this->config->get('config_store_id');
			$settings = \ProtesideSettings::normalize($this->configSettings());

			if ($settings['key'] === '') {
				return;
			}

			// The state map is a store 0 setting, already loaded into the configuration.
			$map = $this->config->get(\ProtesideStorage::STATE_KEY);
			$state = \ProtesideEntitlement::forKey(is_array($map) ? ($map[$store_id] ?? null) : null, $settings['key']);

			$now = time();
			$request = \ProtesidePageMatcher::describeRequest($this->request->get, $_SERVER);

			if ($service->shouldInject($settings, $state, $request, $now)) {
				$output = \ProtesideSnippet::injectAfterHead($output, $service->render($settings, $state));
			}

			if ($settings['enabled'] && !\ProtesideService::$forceInject) {
				$service->scheduleDeferredRefresh($store_id, $settings, $state, $this->siteUrl(), $now);
			}
		} catch (\Throwable $e) {
			// Never break the storefront.
		}
	}

	/**
	 * GET index.php?route=extension/proteside/module/proteside.cron&token=...
	 */
	public function cron(): void {
		$service = $this->service();

		if (!$this->validToken($service)) {
			$this->json(403, ['ok' => false, 'error' => 'invalid token']);

			return;
		}

		$sites = [0 => $this->defaultStoreUrl()];

		foreach ($service->storage()->stores() as $store) {
			$sites[$store['store_id']] = $store['url'];
		}

		$this->json(200, ['ok' => true, 'stores' => $service->refreshAll($sites, empty($this->request->get['force']))]);
	}

	/**
	 * GET index.php?route=extension/proteside/module/proteside.probe&token=...
	 * Renders the header of this store with the snippet forced on and reports
	 * where it ended up.
	 */
	public function probe(): void {
		$service = $this->service();

		if (!$this->validToken($service)) {
			$this->json(403, ['ok' => false, 'error' => 'invalid token']);

			return;
		}

		$settings = \ProtesideSettings::normalize($this->configSettings());

		if (!\ProtesideSnippet::isValidKey($settings['key'])) {
			$this->json(200, ['ok' => false, 'error' => 'no_key']);

			return;
		}

		\ProtesideService::$forceInject = true;

		$header = (string)$this->load->controller('common/header');

		\ProtesideService::$forceInject = false;

		$this->json(200, array_merge(['ok' => true, 'store_id' => (int)$this->config->get('config_store_id')], \ProtesideSnippet::inspect($header)));
	}

	private function service(): \ProtesideService {
		require_once(DIR_EXTENSION . 'proteside/system/library/proteside/proteside.php');

		return new \ProtesideService(new \ProtesideStorage($this->db, DB_PREFIX), DIR_EXTENSION . 'proteside/system/library/proteside');
	}

	/**
	 * Settings of the current store, as loaded by OpenCart (store 0 values
	 * overridden by the store's own).
	 */
	private function configSettings(): array {
		$raw = [];

		foreach (array_keys(\ProtesideSettings::defaults()) as $key) {
			$value = $this->config->get($key);

			if ($value !== null) {
				$raw[$key] = $value;
			}
		}

		return $raw;
	}

	/**
	 * URL of the current store, sent as `site`.
	 */
	private function siteUrl(): string {
		return (string)($this->config->get('config_url') ?: $this->defaultStoreUrl());
	}

	private function defaultStoreUrl(): string {
		return defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER;
	}

	private function validToken(\ProtesideService $service): bool {
		$given = (string)($this->request->get['token'] ?? '');

		return $given !== '' && hash_equals($service->storage()->cronToken(), $given);
	}

	private function json(int $code, array $payload): void {
		http_response_code($code);

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Robots-Tag: noindex');
		$this->response->setOutput(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
	}
}
