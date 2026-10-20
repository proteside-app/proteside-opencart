<?php
namespace Opencart\Admin\Controller\Extension\Proteside\Module;
/**
 * Proteside checkout protection for OpenCart 4.0.2+ / 4.1 - admin.
 *
 * Settings page (per store), status check, storefront probe, install and
 * uninstall (event, cron job and settings).
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class Proteside extends \Opencart\System\Engine\Controller {
	public const EVENT_CODE = 'proteside';
	public const EVENT_TRIGGER = 'catalog/view/common/header/after';
	public const EVENT_ACTION = 'extension/proteside/module/proteside.injectHeader';
	// Runs after the other header events, so nothing is inserted before the snippet.
	public const EVENT_SORT_ORDER = 9999;
	public const CRON_CODE = 'proteside';
	public const CRON_ACTION = 'extension/proteside/cron/proteside';

	public function index(): void {
		$this->load->language('extension/proteside/module/proteside');

		$this->document->setTitle($this->language->get('heading_title'));

		$service = $this->service();
		$storage = $service->storage();
		$store_id = $this->storeId();
		$token = 'user_token=' . $this->session->data['user_token'];

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $token)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $token . '&type=module')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/proteside/module/proteside', $token)
		];

		$data['save'] = $this->url->link('extension/proteside/module/proteside.save', $token . '&store_id=' . $store_id);
		$data['check'] = $this->url->link('extension/proteside/module/proteside.check', $token . '&store_id=' . $store_id);
		$data['probe'] = $this->url->link('extension/proteside/module/proteside.probe', $token . '&store_id=' . $store_id, true);
		$data['back'] = $this->url->link('marketplace/extension', $token . '&type=module');

		$data['success'] = $this->session->data['proteside_success'] ?? '';
		$data['error_warning'] = $this->session->data['proteside_error'] ?? '';

		unset($this->session->data['proteside_success'], $this->session->data['proteside_error']);

		// Stores
		$data['stores'] = [];

		$data['stores'][] = [
			'store_id' => 0,
			'name'     => $this->config->get('config_name') . ' (' . $this->language->get('text_default_store') . ')',
			'href'     => $this->url->link('extension/proteside/module/proteside', $token . '&store_id=0')
		];

		foreach ($storage->stores() as $store) {
			$data['stores'][] = [
				'store_id' => $store['store_id'],
				'name'     => $store['name'],
				'href'     => $this->url->link('extension/proteside/module/proteside', $token . '&store_id=' . $store['store_id'])
			];
		}

		$data['store_id'] = $store_id;
		$data['inherits'] = $store_id !== 0 && !$storage->storeSettings($store_id);

		// Form values
		$raw = array_merge(\ProtesideSettings::defaults(), $storage->effectiveSettings($store_id));
		$settings = \ProtesideSettings::normalize($raw);

		$data['module_proteside_status'] = $settings['enabled'];
		$data['module_proteside_sdk_key'] = (string)$raw['module_proteside_sdk_key'];
		$data['module_proteside_pages'] = $settings['pages'];
		$data['module_proteside_custom_urls'] = implode("\n", $settings['custom']);
		$data['module_proteside_channel'] = $settings['channel'];
		$data['module_proteside_calibration'] = $settings['calibration'] ? (string)$settings['calibration'] : '';
		$data['module_proteside_inline_rules'] = $settings['inline_rules'];
		$data['module_proteside_interval'] = $settings['interval'];
		$data['module_proteside_deferred'] = $settings['deferred'];

		$data['page_groups'] = [];

		foreach (array_keys(\ProtesidePageMatcher::defaultPages()) as $group) {
			$data['page_groups'][] = ['code' => $group, 'label' => $this->language->get('entry_' . $group)];
		}

		$data['intervals'] = [];

		foreach ([6, 12, 24] as $hours) {
			$data['intervals'][] = ['value' => $hours, 'label' => sprintf($this->language->get('text_every_hours'), $hours)];
		}

		// Status card and diagnostics
		$state = $storage->loadState($store_id, $settings['key']);

		$data = array_merge($data, \ProtesideAdminView::build($service, $settings, $state, time(), fn (string $key): string => $this->language->get($key)));

		$data['cron_url'] = $this->catalogUrl(0) . 'index.php?route=extension/proteside/module/proteside.cron&token=' . $storage->cronToken();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/proteside/module/proteside', $data));
	}

	/**
	 * Save (AJAX form). On success the page reloads to show the new status.
	 */
	public function save(): void {
		$this->load->language('extension/proteside/module/proteside');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/proteside/module/proteside')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$service = $this->service();
		$post = $this->decode($this->request->post);

		if (!$json) {
			foreach (\ProtesideSettings::validate($post) as $field => $code) {
				$json['error'][$field] = $this->language->get('error_' . $field);
			}

			if ($json) {
				$json['error']['warning'] = $this->language->get('error_form');
			}
		}

		if (!$json) {
			$store_id = $this->storeId();
			$storage = $service->storage();

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('module_proteside', \ProtesideSettings::sanitize($post), $store_id);

			$settings = \ProtesideSettings::normalize($storage->effectiveSettings($store_id));

			if ($settings['key'] !== '') {
				$service->refresh($store_id, $settings, $this->catalogUrl($store_id));

				$this->session->data['proteside_success'] = $this->language->get('text_success_validated');
			} else {
				$storage->deleteState($store_id);

				$this->session->data['proteside_success'] = $this->language->get('text_success');
			}

			$json['success'] = $this->session->data['proteside_success'];
			$json['redirect'] = $this->url->link('extension/proteside/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * "Check status" button.
	 */
	public function check(): void {
		$this->load->language('extension/proteside/module/proteside');

		$store_id = $this->storeId();

		if (!$this->user->hasPermission('modify', 'extension/proteside/module/proteside')) {
			$this->session->data['proteside_error'] = $this->language->get('error_permission');
		} else {
			$service = $this->service();

			$settings = \ProtesideSettings::normalize($service->storage()->effectiveSettings($store_id));

			if ($settings['key'] !== '') {
				$service->refresh($store_id, $settings, $this->catalogUrl($store_id));
			}

			$this->session->data['proteside_success'] = $this->language->get('text_status_refreshed');
		}

		$this->response->redirect($this->url->link('extension/proteside/module/proteside', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true));
	}

	/**
	 * "Test the installation" button (AJAX): asks the storefront of the
	 * selected store to render its header and reports where the snippet is.
	 */
	public function probe(): void {
		$this->load->language('extension/proteside/module/proteside');

		if (!$this->user->hasPermission('modify', 'extension/proteside/module/proteside')) {
			$json = ['tone' => 'danger', 'text' => $this->language->get('error_permission')];
		} else {
			$store_id = $this->storeId();

			$url = $this->catalogUrl($store_id) . 'index.php?route=extension/proteside/module/proteside.probe&token=' . $this->service()->storage()->cronToken();

			$result = \ProtesideAdminView::fetchProbe($url);

			$json = \ProtesideAdminView::probeMessage($result['json'], $result['error'], fn (string $key): string => $this->language->get($key));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		if (!$this->user->hasPermission('modify', 'extension/module')) {
			return;
		}

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		$this->model_setting_event->addEvent([
			'code'        => self::EVENT_CODE,
			'description' => 'Proteside: inserts the checkout protection snippet first in <head>',
			'trigger'     => self::EVENT_TRIGGER,
			'action'      => self::EVENT_ACTION,
			'status'      => true,
			'sort_order'  => self::EVENT_SORT_ORDER
		]);

		$this->load->model('setting/cron');

		$this->model_setting_cron->deleteCronByCode(self::CRON_CODE);
		$this->model_setting_cron->addCron(self::CRON_CODE, 'Proteside: refreshes the protection status', 'hour', self::CRON_ACTION, true);

		$this->load->model('setting/setting');

		$storage = $this->service()->storage();

		if (!$storage->storeSettings(0)) {
			$this->model_setting_setting->editSetting('module_proteside', \ProtesideSettings::defaults());
		}

		$storage->cronToken();
	}

	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/module')) {
			return;
		}

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$this->load->model('setting/cron');

		$this->model_setting_cron->deleteCronByCode(self::CRON_CODE);

		$this->service()->storage()->purge();
	}

	private function service(): \ProtesideService {
		require_once(DIR_EXTENSION . 'proteside/system/library/proteside/proteside.php');

		return new \ProtesideService(new \ProtesideStorage($this->db, DB_PREFIX), DIR_EXTENSION . 'proteside/system/library/proteside');
	}

	private function storeId(): int {
		return isset($this->request->get['store_id']) ? max(0, (int)$this->request->get['store_id']) : 0;
	}

	/**
	 * Storefront base URL of a store; also sent to Proteside as `site`.
	 */
	private function catalogUrl(int $store_id): string {
		if ($store_id) {
			foreach ($this->service()->storage()->stores() as $store) {
				if ($store['store_id'] == $store_id && $store['url'] !== '') {
					return rtrim($store['url'], '/') . '/';
				}
			}
		}

		return rtrim(HTTP_CATALOG, '/') . '/';
	}

	/**
	 * OpenCart HTML-encodes every request value; undo it for the settings.
	 */
	private function decode(mixed $value): mixed {
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$value[$k] = $this->decode($v);
			}

			return $value;
		}

		return is_string($value) ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : $value;
	}
}
