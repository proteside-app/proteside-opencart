<?php
namespace Opencart\Catalog\Controller\Extension\Proteside\Cron;
/**
 * Proteside checkout protection for OpenCart 4.0.2+ / 4.1 - cron job.
 *
 * Registered on install as the hourly cron "proteside" (Extensions → Cron
 * Jobs). Refreshes the status of every store whose interval has elapsed.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class Proteside extends \Opencart\System\Engine\Controller {
	/**
	 * Called by the OpenCart cron runner with the cron row. A direct request
	 * to this route (no cron id) does nothing.
	 */
	public function index(int $cron_id = 0, string $code = '', string $cycle = '', string $date_added = '', string $date_modified = ''): void {
		if (!$cron_id) {
			return;
		}

		require_once(DIR_EXTENSION . 'proteside/system/library/proteside/proteside.php');

		$service = new \ProtesideService(new \ProtesideStorage($this->db, DB_PREFIX), DIR_EXTENSION . 'proteside/system/library/proteside');

		$sites = [0 => defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER];

		foreach ($service->storage()->stores() as $store) {
			$sites[$store['store_id']] = $store['url'];
		}

		try {
			$service->refreshAll($sites, true);
		} catch (\Throwable $e) {
			// The next run retries.
		}
	}
}
