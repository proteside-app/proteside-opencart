<?php
require __DIR__ . '/bootstrap.php';

$libDir = dirname(__DIR__) . '/lib';
// The service reads the bootstrapper from <lib>/bootstrapper; in the repository it lives one level up.
if (!is_dir($libDir . '/bootstrapper')) {
    $libDir = sys_get_temp_dir() . '/proteside-oc-test-' . getmypid();
    @mkdir($libDir . '/bootstrapper', 0777, true);
    foreach (glob(dirname(__DIR__) . '/bootstrapper/*') as $f) {
        copy($f, $libDir . '/bootstrapper/' . basename($f));
    }
    register_shutdown_function(function () use ($libDir) {
        array_map('unlink', glob($libDir . '/bootstrapper/*'));
        @rmdir($libDir . '/bootstrapper');
        @rmdir($libDir);
    });
}

$statusActive = json_encode(array('active' => true, 'status' => 'active', 'plan' => 'essential', 'graceUntil' => null, 'badgeVisible' => true, 'safeSlug' => 'x', 'cdnBase' => 'https://cdn.proteside.com/v1', 'domainMismatch' => false));
$config = json_encode(array('v' => 2, 'entitled' => true, 'paused' => false, 'mode' => 'block', 'rules' => array(array('type' => 'block', 'target' => 'domain', 'value' => 'evil.example'), array('type' => 'block', 'target' => 'regex', 'value' => 'x')), 'gtmContainers' => array('GTM-ABCD12', 'bad'), 'channel' => 'latest'));

$storage = new MemoryStorage();
$service = new ProtesideService($storage, $libDir);
$client = new FakeApiClient('1.0.0', 'https://loja.example/');
$client->responses = array('/status?' => array(200, $statusActive), '/config?' => array(200, $config));
$service->setClient($client);

$settings = ProtesideSettings::normalize(array('module_proteside_sdk_key' => KEY));
$now = 1800000000;

// refresh: status then config
$state = $service->refresh(0, $settings, 'https://loja.example/', $now);
eq('active', $state['status'], 'refresh stores active');
eq(2, count($client->calls), 'status + config requested');
ok(strpos($client->calls[0], 'https://app.proteside.com/api/sdk/status?key=' . KEY . '&site=https%3A%2F%2Floja.example%2F&platform=opencart&version=1.0.0') === 0, 'status query: key, site, platform=opencart, version');
ok(strpos($client->calls[1], 'https://app.proteside.com/api/sdk/config?key=' . KEY) === 0, 'config query');
eq(1, count($state['inline_rules']), 'config rules filtered');
eq(array('GTM-ABCD12'), $state['inline_gtm'], 'config GTM filtered');
eq('latest', $state['channel'], 'config channel');
eq($state, $storage->states[0], 'state persisted');

// rendering
$html = $service->render($settings, $state);
ok(strpos($html, '"use strict";window.__PROTE_INLINE__={"rules":[{"type":"block","target":"domain","value":"evil.example"}],"gtm":["GTM-ABCD12"]};') !== false, 'inline config emitted');
ok(strpos($html, 'https://cdn.proteside.com/v1/latest/shield.js') !== false, 'channel from dashboard');
$noInline = array_merge($settings, array('inline_rules' => false, 'channel' => 'stable'));
$html2 = $service->render($noInline, $state);
ok(strpos($html2, 'window.__PROTE_INLINE__=') === false && strpos($html2, '/v1/shield.js') !== false, 'inline off and stable override');
eq(ProtesideSnippet::cspHash(file_get_contents(dirname(__DIR__) . '/bootstrapper/bootstrapper.min.js')), ProtesideSnippet::cspHash($service->inlineJs($noInline, $state)), 'inline off: published hash');

// decision
$checkout = array('route' => 'checkout/checkout', 'path' => '/index.php', 'uri' => '/index.php?route=checkout/checkout');
$home = array('route' => 'common/home', 'path' => '/', 'uri' => '/');
ok($service->shouldInject($settings, $state, $checkout, $now), 'inject on checkout');
ok(!$service->shouldInject($settings, $state, $home, $now), 'not on home');
ok(!$service->shouldInject(array_merge($settings, array('enabled' => false)), $state, $checkout, $now), 'disabled: no');
ok(!$service->shouldInject($settings, ProtesideEntitlement::forKey(null, KEY), $checkout, $now), 'never validated: no');
ok(!$service->shouldInject(array_merge($settings, array('key' => '')), $state, $checkout, $now), 'no key: no');
ProtesideService::$forceInject = true;
ok($service->shouldInject($settings, ProtesideEntitlement::forKey(null, KEY), $home, $now), 'probe forces injection');
ok(!$service->shouldInject(array_merge($settings, array('key' => '')), $state, $home, $now), 'probe still needs a valid key');
ProtesideService::$forceInject = false;

// 401
$client->responses = array('/status?' => array(401, '{"active":false,"status":"invalid"}'));
$client->calls = array();
$state = $service->refresh(0, $settings, 'https://loja.example/', $now + 100);
eq('invalid', $state['status'], '401 -> invalid');
eq(1, count($client->calls), 'config not requested when not entitled');
ok(!$service->shouldInject($settings, $state, $checkout, $now + 100), 'invalid: no injection');

// transient after active keeps injecting
$client->responses = array('/status?' => array(200, $statusActive), '/config?' => array(200, $config));
$service->refresh(0, $settings, 'https://loja.example/', $now + 200);
$client->responses = array('/status?' => array(503, 'oops'));
$state = $service->refresh(0, $settings, 'https://loja.example/', $now + 300);
ok($state['last_error'] && $state['status'] === 'active', '503 keeps active with last_error');
ok($service->shouldInject($settings, $state, $checkout, $now + 300), '503 within tolerance: inject');
eq(1, count($state['inline_rules']), 'rules kept through a transient error');
$client->responses = array('/status?' => array(0, ''));
$state = $service->refresh(0, $settings, 'https://loja.example/', $now + 400);
eq('timeout', $state['message'], 'network error message kept');

// malformed key: no HTTP call
$client->calls = array();
$bad = $service->refresh(0, array_merge($settings, array('key' => 'pk_live_x')), 'https://loja.example/', $now);
eq('invalid', $bad['status'], 'malformed key is invalid');
eq(array(), $client->calls, 'malformed key never reaches the API');

// refreshAll: store 0 + store 3 with its own key, store 5 without key
$storage = new MemoryStorage();
$storage->settings[0] = array('module_proteside_sdk_key' => KEY, 'module_proteside_interval' => '12');
$storage->settings[3] = array('module_proteside_sdk_key' => 'pk_test_' . str_repeat('a', 32));
$storage->settings[5] = array('module_proteside_sdk_key' => '');
$service = new ProtesideService($storage, $libDir);
$client = new FakeApiClient('1.0.0', 'x');
$client->responses = array('/status?' => array(200, $statusActive), '/config?' => array(200, $config));
$service->setClient($client);
$out = $service->refreshAll(array(0 => 'https://a.example/', 3 => 'https://b.example/', 5 => 'https://c.example/', 7 => 'https://d.example/'), true, $now);
eq(array(0, 3, 7), array_keys($out), 'stores with a key (own or inherited) are refreshed; empty override skipped');
eq('checked', $out[0]['action'], 'store 0 checked');
ok($out[3]['entitled'], 'store 3 entitled');
$out = $service->refreshAll(array(0 => 'https://a.example/'), true, $now + 3600);
eq('skipped', $out[0]['action'], 'not due: skipped');
$out = $service->refreshAll(array(0 => 'https://a.example/'), false, $now + 3600);
eq('checked', $out[0]['action'], 'force refresh');

// Deferred refresh
$st = $storage->loadState(0, KEY);
$deferredSettings = ProtesideSettings::normalize($storage->effectiveSettings(0));
ok(!$service->scheduleDeferredRefresh(0, $deferredSettings, $st, 'https://a.example/', $now + 3700), 'not due: not scheduled');
ok(!$service->scheduleDeferredRefresh(0, array_merge($deferredSettings, array('deferred' => false)), $st, 'https://a.example/', $now + 50000), 'deferred off: not scheduled');
$lockedState = array_merge($st, array('lock_until' => $now + 50100));
ok(!$service->scheduleDeferredRefresh(0, $deferredSettings, $lockedState, 'https://a.example/', $now + 50000), 'locked: not scheduled');

// runDeferredRefresh body (called directly; the shutdown hook only wraps it). It flushes output buffers first.
$storage->states[0]['checked_at'] = time() - 13 * 3600;
$client->calls = array();
$service->runDeferredRefresh(0, $deferredSettings, 'https://a.example/');
eq(2, count($client->calls), 'deferred refresh calls status and config when due');
eq(0, $storage->states[0]['lock_until'], 'lock released after refresh');
ok(time() - $storage->states[0]['checked_at'] < 5, 'checked_at updated');
$client->calls = array();
$service->runDeferredRefresh(0, $deferredSettings, 'https://a.example/');
eq(0, count($client->calls), 'deferred refresh re-checks the interval and skips');

ok($service->scheduleDeferredRefresh(5, ProtesideSettings::normalize(array('module_proteside_sdk_key' => KEY)), ProtesideEntitlement::forKey(null, KEY), 'https://c.example/', time()), 'due: scheduled');
ok(!$service->scheduleDeferredRefresh(5, ProtesideSettings::normalize(array('module_proteside_sdk_key' => KEY)), ProtesideEntitlement::forKey(null, KEY), 'https://c.example/', time()), 'only once per request');
// Make the scheduled shutdown run a no-op (state fresh) so the test never reaches the network.
$storage->states[5] = array_merge(ProtesideEntitlement::forKey(null, KEY), array('checked_at' => time()));

// Summary and admin view
$state = $storage->loadState(0, KEY);
$sum = ProtesideService::summary($state, time(), 'checked');
ok($sum['entitled'] && $sum['inline_rules'] === 1 && $sum['inline_gtm'] === 1, 'summary counts');

$t = function ($k) { return '[' . $k . ']'; };
$view = ProtesideAdminView::build($service, ProtesideSettings::normalize($storage->effectiveSettings(0)), $state, time(), $t);
eq('active', $view['ps_status_code'], 'admin: status code');
eq('success', $view['ps_status_tone'], 'admin: tone');
ok($view['ps_decision_active'] && $view['ps_decision_text'] === '[diag_decision_active]', 'admin: decision active');
eq('1.1.3', $view['ps_bootstrapper_version'], 'admin: bootstrapper version');
eq('1 / 1', $view['ps_inline_counts'], 'admin: inline counts');
ok(strpos($view['ps_csp_hash'], "'sha256-") === 0, 'admin: CSP hash quoted');
$view = ProtesideAdminView::build($service, ProtesideSettings::normalize(array()), ProtesideEntitlement::forKey(null, ''), time(), $t);
ok(!$view['ps_decision_active'] && $view['ps_status_code'] === 'unknown' && $view['ps_notices'][0]['text'] === '[text_notice_no_key]', 'admin: no key');
eq('[text_less_minute]', ProtesideAdminView::ago(30, $t), 'ago < 1 min');
eq('[text_hours]', ProtesideAdminView::ago(7200, $t), 'ago hours');

$pm = ProtesideAdminView::probeMessage(array('ok' => true, 'head_found' => true, 'injected' => true, 'first_in_head' => true, 'scripts_before' => 0), '', $t);
eq('success', $pm['tone'], 'probe ok');
$pm = ProtesideAdminView::probeMessage(array('ok' => true, 'head_found' => true, 'injected' => true, 'first_in_head' => false, 'scripts_before' => 2), '', $t);
eq('warning', $pm['tone'], 'probe not first');
$pm = ProtesideAdminView::probeMessage(null, 'Could not resolve host', $t);
eq('danger', $pm['tone'], 'probe transport error');
$pm = ProtesideAdminView::probeMessage(array('ok' => false, 'error' => 'no_key'), '', $t);
eq('[probe_no_key]', $pm['text'], 'probe no key');

done('service');
