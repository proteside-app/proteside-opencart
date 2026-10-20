<?php
require __DIR__ . '/bootstrap.php';

$now = 1800000000;
$ok200 = array('ok' => true, 'transient' => false, 'http' => 200, 'status' => 'active', 'active' => true, 'plan' => 'essential', 'grace_until' => null, 'cdn_base' => 'https://cdn.proteside.com/v1', 'domain_mismatch' => false, 'message' => '');
$grace = array_merge($ok200, array('status' => 'grace', 'grace_until' => '2026-11-01'));
$susp = array_merge($ok200, array('status' => 'suspended', 'active' => false));
$inact = array_merge($ok200, array('status' => 'inactive', 'active' => false));
$inval = array('ok' => true, 'transient' => false, 'http' => 401, 'status' => 'invalid', 'active' => false, 'message' => 'HTTP 401');
$down = array('ok' => false, 'transient' => true, 'http' => 503, 'status' => 'connection_error', 'active' => false, 'message' => 'HTTP 503');

$fresh = ProtesideEntitlement::forKey(null, KEY);

// Decision table
ok(ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($fresh, $ok200, $now), $now), 'active + OK: inject');
ok(ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($fresh, $grace, $now), $now), 'grace + OK: inject');
ok(!ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($fresh, $susp, $now), $now), 'suspended: no');
ok(!ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($fresh, $inact, $now), $now), 'inactive: no');
ok(!ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($fresh, $inval, $now), $now), 'invalid (401): no');
ok(!ProtesideEntitlement::shouldInject($fresh, $now), 'never validated: no');
ok(!ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($fresh, $down, $now), $now), 'never validated + API down: no (fail-closed)');

$active = ProtesideEntitlement::applyStatus($fresh, $ok200, $now);
$t47 = $now + 47 * 3600;
$downAt47 = ProtesideEntitlement::applyStatus($active, $down, $t47);
ok(ProtesideEntitlement::shouldInject($downAt47, $t47), 'active, API down, last success 47 h ago: inject (tolerance)');
eq('active', $downAt47['status'], 'transient keeps the definitive status');
ok($downAt47['last_error'] === true, 'transient marks last_error');
eq($now, $downAt47['last_success_at'], 'transient keeps last_success_at');
$t49 = $now + 49 * 3600;
ok(!ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($active, $down, $t49), $t49), 'active, API down, last success 49 h ago: no');
$t48 = $now + 48 * 3600;
ok(ProtesideEntitlement::shouldInject(ProtesideEntitlement::applyStatus($active, $down, $t48), $t48), 'exactly 48 h: still tolerated');

$suspThenDown = ProtesideEntitlement::applyStatus(ProtesideEntitlement::applyStatus($fresh, $susp, $now), $down, $now + 60);
ok(!ProtesideEntitlement::shouldInject($suspThenDown, $now + 60), 'suspended then API down: still no');
$back = ProtesideEntitlement::applyStatus($downAt47, $ok200, $t47 + 60);
ok(ProtesideEntitlement::shouldInject($back, $t47 + 60) && $back['last_error'] === false, 'recovery clears last_error');

// 200 with an active flag that contradicts the status
$weird = ProtesideEntitlement::applyStatus($fresh, array_merge($ok200, array('active' => false)), $now);
ok(!ProtesideEntitlement::shouldInject($weird, $now), 'status active but active:false: no');
$bogus = ProtesideEntitlement::applyStatus($fresh, array_merge($ok200, array('status' => 'weird')), $now);
eq('invalid', $bogus['status'], 'unknown status normalised to invalid');

// Fields
eq('essential', $active['plan'], 'plan stored');
eq('https://cdn.proteside.com/v1', $active['cdn_base'], 'cdn_base stored');
$mm = ProtesideEntitlement::applyStatus($fresh, array_merge($ok200, array('domain_mismatch' => true)), $now);
ok($mm['domain_mismatch'] && ProtesideEntitlement::shouldInject($mm, $now), 'domain mismatch is only advisory');

// Config
$cfg = array('ok' => true, 'rules' => array(array('type' => 'block', 'target' => 'domain', 'value' => 'x.example')), 'gtm' => array('GTM-AAAA11'), 'channel' => 'latest');
$withCfg = ProtesideEntitlement::applyConfig($active, $cfg, $now);
eq(1, count($withCfg['inline_rules']), 'rules cached');
eq(array('GTM-AAAA11'), $withCfg['inline_gtm'], 'gtm cached');
eq('latest', $withCfg['channel'], 'channel cached');
$failed = ProtesideEntitlement::applyConfig($withCfg, array('ok' => false), $now + 10);
eq($withCfg['inline_rules'], $failed['inline_rules'], 'failed config fetch keeps rules');
$cleared = ProtesideEntitlement::applyStatus($withCfg, $susp, $now + 20);
eq(array(), $cleared['inline_rules'], 'losing entitlement clears cached rules');

// Display status
eq('active', ProtesideEntitlement::displayStatus($active, $now), 'display active');
eq('unknown', ProtesideEntitlement::displayStatus($fresh, $now), 'display unknown');
eq('connection_error', ProtesideEntitlement::displayStatus(ProtesideEntitlement::applyStatus($fresh, $down, $now), $now), 'display connection error when never validated');
eq('active', ProtesideEntitlement::displayStatus($downAt47, $t47), 'display active within tolerance');
eq('connection_error', ProtesideEntitlement::displayStatus(ProtesideEntitlement::applyStatus($active, $down, $t49), $t49), 'display connection error after tolerance');
eq('invalid', ProtesideEntitlement::displayStatus(ProtesideEntitlement::applyStatus($fresh, $inval, $now), $now), 'display invalid');

// Channel
eq('stable', ProtesideEntitlement::effectiveChannel('auto', $active), 'auto without remote channel: stable');
eq('latest', ProtesideEntitlement::effectiveChannel('auto', $withCfg), 'auto follows the dashboard');
eq('stable', ProtesideEntitlement::effectiveChannel('stable', $withCfg), 'manual override wins');
eq('latest', ProtesideEntitlement::effectiveChannel('latest', $active), 'manual latest');

// Interval and lock
ok(ProtesideEntitlement::isDue($fresh, 12, $now), 'never checked: due');
ok(!ProtesideEntitlement::isDue($active, 12, $now + 11 * 3600), '11 h after a check with 12 h interval: not due');
ok(ProtesideEntitlement::isDue($active, 12, $now + 12 * 3600), '12 h: due');
ok(ProtesideEntitlement::isDue($active, 6, $now + 6 * 3600 + 1), '6 h interval');
$locked = array_merge($active, array('lock_until' => $now + 100));
ok(ProtesideEntitlement::isLocked($locked, $now), 'locked');
ok(!ProtesideEntitlement::isLocked($locked, $now + 101), 'lock expires');

// Key binding
$other = ProtesideEntitlement::forKey($active, 'pk_live_' . str_repeat('1', 32));
eq('unknown', $other['status'], 'state of another key is discarded');
$same = ProtesideEntitlement::forKey(json_encode($active), KEY);
eq('active', $same['status'], 'state of the same key is kept (JSON input)');
$garbage = ProtesideEntitlement::decode(array('inline_rules' => 'x', 'inline_gtm' => null));
ok($garbage['inline_rules'] === array() && $garbage['inline_gtm'] === array(), 'decode repairs non-array rules');

done('entitlement');
