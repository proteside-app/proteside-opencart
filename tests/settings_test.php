<?php
require __DIR__ . '/bootstrap.php';

$n = ProtesideSettings::normalize(array());
ok($n['enabled'] && $n['key'] === '' && $n['channel'] === 'auto' && $n['interval'] === 12 && $n['inline_rules'] && $n['deferred'], 'defaults');
ok($n['pages']['checkout'] && $n['pages']['order_confirmation'] && !$n['pages']['cart'] && !$n['pages']['all_store'], 'default pages');
eq(0, $n['calibration'], 'no calibration by default');

$n = ProtesideSettings::normalize(array('module_proteside_channel' => 'beta', 'module_proteside_interval' => '7', 'module_proteside_calibration' => '200'));
ok($n['channel'] === 'auto' && $n['interval'] === 12 && $n['calibration'] === 0, 'out-of-range values fall back');

eq(0, ProtesideSettings::calibration('9'), 'calibration 9');
eq(10, ProtesideSettings::calibration('10'), 'calibration 10');
eq(120, ProtesideSettings::calibration(' 120 '), 'calibration 120');
eq(0, ProtesideSettings::calibration('30s'), 'calibration with unit');
eq(0, ProtesideSettings::calibration('-20'), 'negative calibration');

eq(array(), ProtesideSettings::validate(array('module_proteside_sdk_key' => '', 'module_proteside_calibration' => '')), 'empty key and calibration are valid');
eq(array('sdk_key' => 'invalid'), ProtesideSettings::validate(array('module_proteside_sdk_key' => 'pk_live_123')), 'malformed key');
eq(array(), ProtesideSettings::validate(array('module_proteside_sdk_key' => '  ' . KEY . ' ')), 'surrounding spaces are tolerated');
eq(array('calibration' => 'range'), ProtesideSettings::validate(array('module_proteside_calibration' => '5')), 'calibration out of range');

$post = array(
    'module_proteside_status' => '1',
    'module_proteside_sdk_key' => ' ' . KEY . ' ',
    'module_proteside_pages' => array('checkout' => '1', 'order_confirmation' => '0', 'cart' => '1', 'bogus' => '1'),
    'module_proteside_custom_urls' => "/a\r\n\r\n/b\n/a",
    'module_proteside_channel' => 'latest',
    'module_proteside_calibration' => '30',
    'module_proteside_inline_rules' => '0',
    'module_proteside_interval' => '24',
    'module_proteside_deferred' => '0',
    'other_key' => 'x',
);
$clean = ProtesideSettings::sanitize($post);
eq(KEY, $clean['module_proteside_sdk_key'], 'key trimmed');
eq(array('checkout' => 1, 'order_confirmation' => 0, 'cart' => 1, 'shop' => 0, 'account' => 0, 'login' => 0, 'all_store' => 0), $clean['module_proteside_pages'], 'pages: every group present, unknown dropped');
eq("/a\n/b", $clean['module_proteside_custom_urls'], 'custom URLs normalised');
ok($clean['module_proteside_channel'] === 'latest' && $clean['module_proteside_calibration'] === '30' && $clean['module_proteside_inline_rules'] === '0' && $clean['module_proteside_interval'] === '24' && $clean['module_proteside_deferred'] === '0', 'options kept');
ok(!isset($clean['other_key']), 'foreign keys dropped');
eq(array_keys(ProtesideSettings::defaults()), array_keys($clean), 'sanitize writes exactly the known keys');
foreach (array_keys($clean) as $k) {
    ok(strpos($k, 'module_proteside_') === 0, "key $k has the module_proteside_ prefix (required by editSetting)");
}

$off = ProtesideSettings::sanitize(array('module_proteside_sdk_key' => 'garbage'));
eq('', $off['module_proteside_sdk_key'], 'invalid key is never stored');
eq('0', $off['module_proteside_status'], 'missing checkbox means off');

// Round trip: what the form stores is what the storefront reads
$round = ProtesideSettings::normalize($clean);
ok($round['key'] === KEY && $round['pages']['cart'] && !$round['pages']['order_confirmation'] && $round['custom'] === array('/a', '/b') && $round['calibration'] === 30, 'round trip');

done('settings');
