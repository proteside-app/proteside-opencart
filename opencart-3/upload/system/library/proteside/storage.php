<?php
/**
 * Proteside checkout protection for OpenCart.
 *
 * Persistence on the core `setting` table, through the OpenCart DB object
 * (`query()` and `escape()`, identical in 3.0 and 4.x):
 *
 *   code module_proteside  per store   merchant settings (written by the admin form)
 *   code proteside_state   store 0     JSON map store_id => entitlement state
 *   code proteside_cron    store 0     token of the cron / probe URLs
 *
 * The state is kept in one row so the storefront reads it from the already
 * loaded configuration, without an extra query on the render path.
 *
 * @author    Proteside
 * @copyright 2026 Proteside
 * @license   GPL-3.0-or-later
 */
class ProtesideStorage
{
    const SETTINGS_CODE = 'module_proteside';
    const STATE_CODE = 'proteside_state';
    const STATE_KEY = 'proteside_state';
    const CRON_CODE = 'proteside_cron';
    const CRON_KEY = 'proteside_cron_token';

    /** @var object OpenCart DB */
    private $db;

    /** @var string */
    private $prefix;

    /**
     * @param object $db OpenCart DB object
     * @param string $prefix DB_PREFIX
     */
    public function __construct($db, $prefix)
    {
        $this->db = $db;
        $this->prefix = (string) $prefix;
    }

    /**
     * @param int $storeId
     * @param string $key SDK key the state must belong to
     *
     * @return array
     */
    public function loadState($storeId, $key)
    {
        $map = $this->loadStateMap();
        $raw = isset($map[(string) (int) $storeId]) ? $map[(string) (int) $storeId] : null;

        return ProtesideEntitlement::forKey($raw, $key);
    }

    /**
     * @param int $storeId
     * @param array $state
     */
    public function saveState($storeId, array $state)
    {
        $map = $this->loadStateMap();
        $map[(string) (int) $storeId] = $state;
        $this->setValue(self::STATE_CODE, self::STATE_KEY, $map, 0);
    }

    /**
     * @param int $storeId
     */
    public function deleteState($storeId)
    {
        $map = $this->loadStateMap();
        unset($map[(string) (int) $storeId]);
        $this->setValue(self::STATE_CODE, self::STATE_KEY, $map, 0);
    }

    /**
     * @return array store_id => state
     */
    public function loadStateMap()
    {
        $map = $this->getValue(self::STATE_CODE, self::STATE_KEY, 0);

        return is_array($map) ? $map : array();
    }

    /**
     * Token of the cron and probe URLs; created on first use.
     *
     * @return string
     */
    public function cronToken()
    {
        $token = $this->getValue(self::CRON_CODE, self::CRON_KEY, 0);
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            $token = self::randomToken();
            $this->setValue(self::CRON_CODE, self::CRON_KEY, $token, 0);
        }

        return $token;
    }

    /**
     * Merchant settings of one store, exactly as stored (no fallback).
     *
     * @param int $storeId
     *
     * @return array key => value
     */
    public function storeSettings($storeId)
    {
        $query = $this->db->query('SELECT `key`, `value`, `serialized` FROM `' . $this->prefix . 'setting`'
            . " WHERE `store_id` = '" . (int) $storeId . "' AND `code` = '" . $this->db->escape(self::SETTINGS_CODE) . "'");
        $out = array();
        foreach ($query->rows as $row) {
            $out[$row['key']] = $row['serialized'] ? json_decode($row['value'], true) : $row['value'];
        }

        return $out;
    }

    /**
     * Settings in effect for a store: store 0 values overridden by the
     * store's own, which is how OpenCart loads the configuration.
     *
     * @param int $storeId
     *
     * @return array key => value
     */
    public function effectiveSettings($storeId)
    {
        $settings = $this->storeSettings(0);
        if ((int) $storeId !== 0) {
            $settings = array_merge($settings, $this->storeSettings($storeId));
        }

        return $settings;
    }

    /**
     * Additional stores (store 0 is not in this table).
     *
     * @return array list of ['store_id' => int, 'name' => string, 'url' => string]
     */
    public function stores()
    {
        $query = $this->db->query('SELECT * FROM `' . $this->prefix . 'store` ORDER BY `store_id` ASC');
        $out = array();
        foreach ($query->rows as $row) {
            $url = isset($row['ssl']) && $row['ssl'] !== '' ? $row['ssl'] : (isset($row['url']) ? $row['url'] : '');
            $out[] = array('store_id' => (int) $row['store_id'], 'name' => (string) $row['name'], 'url' => (string) $url);
        }

        return $out;
    }

    /**
     * Remove everything this extension stored (uninstall).
     */
    public function purge()
    {
        foreach (array(self::SETTINGS_CODE, self::STATE_CODE, self::CRON_CODE) as $code) {
            $this->db->query('DELETE FROM `' . $this->prefix . "setting` WHERE `code` = '" . $this->db->escape($code) . "'");
        }
    }

    /**
     * @param string $code
     * @param string $key
     * @param int $storeId
     *
     * @return mixed null when missing
     */
    public function getValue($code, $key, $storeId)
    {
        $query = $this->db->query('SELECT `value`, `serialized` FROM `' . $this->prefix . 'setting`'
            . " WHERE `store_id` = '" . (int) $storeId . "' AND `code` = '" . $this->db->escape($code) . "'"
            . " AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");
        if (!$query->num_rows) {
            return null;
        }

        return $query->row['serialized'] ? json_decode($query->row['value'], true) : $query->row['value'];
    }

    /**
     * @param string $code
     * @param string $key
     * @param mixed $value arrays are stored serialized (JSON), like editSetting()
     * @param int $storeId
     */
    public function setValue($code, $key, $value, $storeId)
    {
        $serialized = is_array($value) ? 1 : 0;
        if ($serialized) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }
        $where = " WHERE `store_id` = '" . (int) $storeId . "' AND `code` = '" . $this->db->escape($code) . "'"
            . " AND `key` = '" . $this->db->escape($key) . "'";
        $this->db->query('DELETE FROM `' . $this->prefix . 'setting`' . $where);
        $this->db->query('INSERT INTO `' . $this->prefix . 'setting` SET `store_id` = \'' . (int) $storeId . '\','
            . " `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "',"
            . " `value` = '" . $this->db->escape((string) $value) . "', `serialized` = '" . $serialized . "'");
    }

    /**
     * @return string 40 hex chars
     */
    public static function randomToken()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(20));
        }

        return sha1(uniqid((string) mt_rand(), true) . microtime());
    }
}
