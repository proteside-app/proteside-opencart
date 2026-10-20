<?php
/**
 * Minimal test harness (plain PHP, no OpenCart, no Composer).
 * Run every suite with: php tests/run.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/lib/proteside.php';

$GLOBALS['proteside_pass'] = 0;
$GLOBALS['proteside_fail'] = array();

/**
 * @param bool $cond
 * @param string $label
 */
function ok($cond, $label)
{
    if ($cond) {
        $GLOBALS['proteside_pass']++;
    } else {
        $GLOBALS['proteside_fail'][] = $label;
        echo "  FAIL: $label\n";
    }
}

/**
 * @param mixed $expected
 * @param mixed $actual
 * @param string $label
 */
function eq($expected, $actual, $label)
{
    ok($expected === $actual, $label . ($expected === $actual ? '' : ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'));
}

/**
 * Print the result and exit with the right code.
 *
 * @param string $suite
 */
function done($suite)
{
    $fail = count($GLOBALS['proteside_fail']);
    printf("%s: %d passed, %d failed\n", $suite, $GLOBALS['proteside_pass'], $fail);
    exit($fail ? 1 : 0);
}

/** API client whose HTTP layer returns canned responses. */
class FakeApiClient extends ProtesideApiClient
{
    /** @var array url-substring => [code, body] */
    public $responses = array();

    /** @var array requested URLs */
    public $calls = array();

    protected function get($url)
    {
        $this->calls[] = $url;
        foreach ($this->responses as $needle => $resp) {
            if (strpos($url, $needle) !== false) {
                if ($resp[0] === 0) {
                    return array('code' => 0, 'body' => '', 'error' => 'timeout');
                }

                return array('code' => $resp[0], 'body' => $resp[1], 'error' => '');
            }
        }

        return array('code' => 0, 'body' => '', 'error' => 'no route');
    }
}

/** Storage kept in memory (the SQL layer is covered by the OpenCart end-to-end run). */
class MemoryStorage extends ProtesideStorage
{
    public $states = array();
    public $settings = array();
    public $extraStores = array();

    public function __construct()
    {
    }

    public function loadState($storeId, $key)
    {
        return ProtesideEntitlement::forKey(isset($this->states[$storeId]) ? $this->states[$storeId] : null, $key);
    }

    public function saveState($storeId, array $state)
    {
        $this->states[$storeId] = $state;
    }

    public function deleteState($storeId)
    {
        unset($this->states[$storeId]);
    }

    public function storeSettings($storeId)
    {
        return isset($this->settings[$storeId]) ? $this->settings[$storeId] : array();
    }

    public function stores()
    {
        return $this->extraStores;
    }

    public function cronToken()
    {
        return str_repeat('ab', 20);
    }
}

const KEY = 'pk_live_3f9c1a7b2e4d6f8a0c1e3b5d7f9a1c3e';
