<?php
/**
 * Proteside checkout protection for OpenCart 3.0.x - admin.
 *
 * Settings page (per store), status check, storefront probe, install and
 * uninstall (event and settings).
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ControllerExtensionModuleProteside extends Controller {
	const EVENT_CODE = 'proteside';
	const EVENT_TRIGGER = 'catalog/view/common/header/after';
	const EVENT_ACTION = 'extension/module/proteside/injectHeader';
	// Runs after the other header events, so nothing is inserted before the snippet.
	const EVENT_SORT_ORDER = 9999;

	private $error = array();

	public function index() {
		$this->load->language('extension/module/proteside');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$service = $this->service();
		$storage = $service->storage();
		$store_id = $this->storeId();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$data = ProtesideSettings::sanitize($this->decode($this->request->post));

			$this->model_setting_setting->editSetting('module_proteside', $data, $store_id);

			$settings = ProtesideSettings::normalize($storage->effectiveSettings($store_id));

			if ($settings['key'] !== '') {
				$service->refresh($store_id, $settings, $this->siteUrl($store_id));

				$this->session->data['success'] = $this->language->get('text_success_validated');
			} else {
				$storage->deleteState($store_id);

				$this->session->data['success'] = $this->language->get('text_success');
			}

			$this->response->redirect($this->url->link('extension/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/proteside', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true);
		$data['check'] = $this->url->link('extension/module/proteside/check', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true);
		$data['probe'] = str_replace('&amp;', '&', $this->url->link('extension/module/proteside/probe', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true));
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];

			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}
		$data['error_sdk_key'] = isset($this->error['sdk_key']) ? $this->error['sdk_key'] : '';
		$data['error_calibration'] = isset($this->error['calibration']) ? $this->error['calibration'] : '';

		// Stores
		$data['stores'] = array();

		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->config->get('config_name') . ' (' . $this->language->get('text_default_store') . ')',
			'href'     => $this->url->link('extension/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=0', true)
		);

		foreach ($storage->stores() as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name'],
				'href'     => $this->url->link('extension/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store['store_id'], true)
			);
		}

		$data['store_id'] = $store_id;
		$data['inherits'] = $store_id !== 0 && !$storage->storeSettings($store_id);

		// Form values: the submitted ones after a validation error, otherwise the stored ones
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$raw = ProtesideSettings::sanitize($this->decode($this->request->post));
			$raw['module_proteside_sdk_key'] = isset($this->request->post['module_proteside_sdk_key']) ? trim($this->request->post['module_proteside_sdk_key']) : '';
			$raw['module_proteside_calibration'] = isset($this->request->post['module_proteside_calibration']) ? trim($this->request->post['module_proteside_calibration']) : '';
		} else {
			$raw = array_merge(ProtesideSettings::defaults(), $storage->effectiveSettings($store_id));
		}

		$settings = ProtesideSettings::normalize($storage->effectiveSettings($store_id));

		$data['module_proteside_status'] = (bool)$raw['module_proteside_status'];
		$data['module_proteside_sdk_key'] = (string)$raw['module_proteside_sdk_key'];
		$data['module_proteside_pages'] = ProtesidePageMatcher::normalizePages($raw['module_proteside_pages']);
		$data['module_proteside_custom_urls'] = implode("\n", ProtesidePageMatcher::normalizeCustom($raw['module_proteside_custom_urls']));
		$data['module_proteside_channel'] = (string)$raw['module_proteside_channel'];
		$data['module_proteside_calibration'] = (string)$raw['module_proteside_calibration'];
		$data['module_proteside_inline_rules'] = (bool)$raw['module_proteside_inline_rules'];
		$data['module_proteside_interval'] = (int)$raw['module_proteside_interval'];
		$data['module_proteside_deferred'] = (bool)$raw['module_proteside_deferred'];

		$data['page_groups'] = array();

		foreach (array_keys(ProtesidePageMatcher::defaultPages()) as $group) {
			$data['page_groups'][] = array('code' => $group, 'label' => $this->language->get('entry_' . $group));
		}

		$data['intervals'] = array();

		foreach (array(6, 12, 24) as $hours) {
			$data['intervals'][] = array('value' => $hours, 'label' => sprintf($this->language->get('text_every_hours'), $hours));
		}

		// Status card and diagnostics
		$language = $this->language;

		$state = $storage->loadState($store_id, $settings['key']);

		$data = array_merge($data, ProtesideAdminView::build($service, $settings, $state, time(), function ($key) use ($language) {
			return $language->get($key);
		}));

		$data['cron_url'] = $this->catalogUrl(0) . 'index.php?route=extension/module/proteside/cron&token=' . $storage->cronToken();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/proteside', $data));
	}

	/**
	 * "Check status" button.
	 */
	public function check() {
		$this->load->language('extension/module/proteside');

		$store_id = $this->storeId();

		if (!$this->user->hasPermission('modify', 'extension/module/proteside')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$service = $this->service();

			$settings = ProtesideSettings::normalize($service->storage()->effectiveSettings($store_id));

			if ($settings['key'] !== '') {
				$service->refresh($store_id, $settings, $this->siteUrl($store_id));
			}

			$this->session->data['success'] = $this->language->get('text_status_refreshed');
		}

		$this->response->redirect($this->url->link('extension/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true));
	}

	/**
	 * "Test the installation" button (AJAX): asks the storefront of the
	 * selected store to render its header and reports where the snippet is.
	 */
	public function probe() {
		$this->load->language('extension/module/proteside');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/proteside')) {
			$json = array('tone' => 'danger', 'text' => $this->language->get('error_permission'));
		} else {
			$store_id = $this->storeId();

			$storage = $this->service()->storage();

			$url = $this->catalogUrl($store_id) . 'index.php?route=extension/module/proteside/probe&token=' . $storage->cronToken();

			$result = ProtesideAdminView::fetchProbe($url);

			$language = $this->language;

			$json = ProtesideAdminView::probeMessage($result['json'], $result['error'], function ($key) use ($language) {
				return $language->get($key);
			});
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install() {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		$this->model_setting_event->addEvent(self::EVENT_CODE, self::EVENT_TRIGGER, self::EVENT_ACTION, 1, self::EVENT_SORT_ORDER);

		$this->load->model('setting/setting');

		$storage = $this->service()->storage();

		if (!$storage->storeSettings(0)) {
			$this->model_setting_setting->editSetting('module_proteside', ProtesideSettings::defaults());
		}

		$storage->cronToken();
	}

	public function uninstall() {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$this->service()->storage()->purge();
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/proteside')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach (ProtesideSettings::validate($this->decode($this->request->post)) as $field => $code) {
			$this->error[$field] = $this->language->get('error_' . $field);
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	/**
	 * @return ProtesideService
	 */
	private function service() {
		require_once(DIR_SYSTEM . 'library/proteside/proteside.php');

		return new ProtesideService(new ProtesideStorage($this->db, DB_PREFIX), DIR_SYSTEM . 'library/proteside');
	}

	/**
	 * @return int
	 */
	private function storeId() {
		return isset($this->request->get['store_id']) ? max(0, (int)$this->request->get['store_id']) : 0;
	}

	/**
	 * Store URL sent to Proteside as `site`.
	 *
	 * @param int $store_id
	 *
	 * @return string
	 */
	private function siteUrl($store_id) {
		return $this->catalogUrl($store_id);
	}

	/**
	 * Storefront base URL of a store (HTTPS when configured).
	 *
	 * @param int $store_id
	 *
	 * @return string
	 */
	private function catalogUrl($store_id) {
		if ($store_id) {
			foreach ($this->service()->storage()->stores() as $store) {
				if ($store['store_id'] == $store_id && $store['url'] !== '') {
					return rtrim($store['url'], '/') . '/';
				}
			}
		}

		$url = defined('HTTPS_CATALOG') && HTTPS_CATALOG ? HTTPS_CATALOG : HTTP_CATALOG;

		return rtrim($url, '/') . '/';
	}

	/**
	 * OpenCart HTML-encodes every request value; undo it for the settings.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function decode($value) {
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$value[$k] = $this->decode($v);
			}

			return $value;
		}

		return is_string($value) ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : $value;
	}
}
