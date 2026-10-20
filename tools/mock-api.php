<?php
/**
 * Proteside checkout protection for OpenCart - mock of the Proteside SDK API
 * for staging and end-to-end tests. Never used in production.
 *
 *   php -S 127.0.0.1:8199 tools/mock-api.php
 *   // in config.php and admin/config.php of the test store:
 *   define('PROTESIDE_API_BASE', 'http://127.0.0.1:8199/api/sdk');
 *
 * Keys ending in "dead" answer 401 (invalid), keys ending in "beef" are
 * suspended, any other well-formed key is active. Write "grace" or "down" to
 * tools/mock-api.mode to switch every key to grace period or HTTP 503, and a
 * number of seconds to tools/mock-api.delay to slow the answers down.
 * Every request is appended to tools/mock-api.log.
 */
$dir = __DIR__;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
parse_str((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $q);
file_put_contents($dir . '/mock-api.log', date('c') . ' ' . $_SERVER['REQUEST_URI'] . ' UA=' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') . "\n", FILE_APPEND);
if (is_file($dir . '/mock-api.delay')) {
    sleep((int) file_get_contents($dir . '/mock-api.delay'));
}
$mode = is_file($dir . '/mock-api.mode') ? trim(file_get_contents($dir . '/mock-api.mode')) : 'active';
$key = isset($q['key']) ? $q['key'] : '';
header('Content-Type: application/json');
if ($mode === 'down') {
    http_response_code(503);
    echo '{"error":"down"}';
    return;
}
if (!preg_match('/^pk_(live|test)_[0-9a-f]{32}$/D', $key) || substr($key, -4) === 'dead') {
    http_response_code(401);
    echo '{"active":false,"status":"invalid"}';
    return;
}
$suspended = substr($key, -4) === 'beef';
if ($path === '/api/sdk/status') {
    echo json_encode(array(
        'active' => !$suspended,
        'status' => $suspended ? 'suspended' : ($mode === 'grace' ? 'grace' : 'active'),
        'plan' => 'essential',
        'graceUntil' => $mode === 'grace' ? '2026-11-01' : null,
        'badgeVisible' => true,
        'safeSlug' => 'test-store',
        'cdnBase' => 'https://cdn.proteside.com/v1',
        'domainMismatch' => isset($q['site']) && strpos($q['site'], '127.0.0.1') !== false,
    ));
} elseif ($path === '/api/sdk/config') {
    echo json_encode(array(
        'v' => 2, 'entitled' => true, 'paused' => false, 'mode' => 'block',
        'rules' => array(
            array('type' => 'block', 'target' => 'domain', 'value' => 'skimmer.example'),
            array('type' => 'block', 'target' => 'src', 'value' => 'https://evil.example/</script>.js'),
            array('type' => 'monitor', 'target' => 'domain', 'value' => 'ignored.example'),
        ),
        'gtmContainers' => array('GTM-TEST123', 'not-gtm'),
        'channel' => 'stable',
    ));
} else {
    http_response_code(404);
    echo '{}';
}
