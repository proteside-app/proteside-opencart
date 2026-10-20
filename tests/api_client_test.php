<?php
require __DIR__ . '/bootstrap.php';

$c = new FakeApiClient('1.0.0', 'https://loja.example/');
eq('Proteside-OpenCart/1.0.0; https://loja.example/', $c->userAgent(), 'User-Agent');

$c->responses = array('/status?' => array(200, '{"active":true,"status":"grace","plan":"pro","graceUntil":"2026-11-01","cdnBase":"https://cdn.proteside.com/v1","domainMismatch":true}'));
$r = $c->fetchStatus(KEY);
ok($r['ok'] && !$r['transient'] && $r['status'] === 'grace' && $r['active'] && $r['plan'] === 'pro' && $r['grace_until'] === '2026-11-01' && $r['domain_mismatch'], 'grace normalised');

$c->responses = array('/status?' => array(200, '{"active":true}'));
$r = $c->fetchStatus(KEY);
eq('active', $r['status'], 'missing status derives from active');

$c->responses = array('/status?' => array(200, 'not json'));
$r = $c->fetchStatus(KEY);
ok($r['transient'], 'non-JSON 200 is transient');

foreach (array(429, 500, 502, 503) as $code) {
    $c->responses = array('/status?' => array($code, '{}'));
    $r = $c->fetchStatus(KEY);
    ok($r['transient'] && !$r['ok'], "HTTP $code is transient");
}
$c->responses = array('/status?' => array(401, '{"active":false,"status":"invalid"}'));
$r = $c->fetchStatus(KEY);
ok($r['ok'] && !$r['transient'] && $r['status'] === 'invalid', '401 is definitive invalid');
$c->responses = array('/status?' => array(404, '{}'));
ok($c->fetchStatus(KEY)['transient'], '404 is not definitive');

$c->calls = array();
$r = $c->fetchStatus('pk_live_nope');
ok($r['status'] === 'invalid' && !$c->calls, 'malformed key: invalid without a request');

$c->responses = array('/config?' => array(200, '{"rules":[{"type":"allow","target":"src","value":"https://a/b.js"}],"gtmContainers":["GTM-XYZ123"],"channel":"stable"}'));
$r = $c->fetchConfig(KEY);
ok($r['ok'] && count($r['rules']) === 1 && $r['gtm'] === array('GTM-XYZ123') && $r['channel'] === 'stable', 'config normalised');
$c->responses = array('/config?' => array(500, ''));
ok(!$c->fetchConfig(KEY)['ok'], 'config 500 not ok');
$c->responses = array('/config?' => array(200, '{"channel":"nightly"}'));
eq(null, $c->fetchConfig(KEY)['channel'], 'unknown channel ignored');

done('api_client');
