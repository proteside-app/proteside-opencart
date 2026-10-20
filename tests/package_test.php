<?php
require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

/**
 * @param string $file
 *
 * @return array
 */
function lang_keys($file)
{
    $_ = array();
    include $file;

    return $_;
}

// Same keys in every language, no empty value
$en = lang_keys($root . '/lang/en-gb.php');
foreach (array('pt-br', 'es-es') as $code) {
    $other = lang_keys($root . '/lang/' . $code . '.php');
    eq(array(), array_values(array_diff(array_keys($en), array_keys($other))), "$code has every en-gb key");
    eq(array(), array_values(array_diff(array_keys($other), array_keys($en))), "$code has no extra key");
    foreach ($other as $k => $v) {
        if ($v === '') {
            ok(false, "$code: $k is empty");
        }
        if (substr_count($v, '%') !== substr_count($en[$k], '%')) {
            ok(false, "$code: $k has different placeholders");
        }
    }
}

// Every key used by the templates and controllers exists (core OpenCart keys excepted)
// error_warning is a template variable; keys ending in _ are concatenated prefixes.
$core = array('text_home', 'button_cancel', 'button_back', 'text_enabled', 'text_disabled', 'error_warning');
$used = array();
$files = array_merge(
    glob($root . '/opencart-3/upload/admin/view/template/extension/module/*.twig'),
    glob($root . '/opencart-4/proteside/admin/view/template/module/*.twig')
);
foreach ($files as $f) {
    preg_match_all('/\{\{\s*((?:text|entry|help|diag|button|probe|status|error)_[a-z_]+)/', file_get_contents($f), $m);
    $used = array_merge($used, $m[1]);
}
$files = array_merge(
    glob($root . '/opencart-3/upload/admin/controller/extension/module/*.php'),
    glob($root . '/opencart-4/proteside/admin/controller/module/*.php'),
    glob($root . '/lib/*.php')
);
foreach ($files as $f) {
    preg_match_all("/(?:language->get|\\\$t)\(\s*'([a-z_]+)'/", file_get_contents($f), $m);
    $used = array_merge($used, $m[1]);
    preg_match_all("/call_user_func\(\\\$t, '([a-z_]+)'\)/", file_get_contents($f), $m);
    $used = array_merge($used, $m[1]);
}
$used = array_unique($used);
ok(count($used) > 40, 'found the language keys in use (' . count($used) . ')');
foreach ($used as $key) {
    if (!in_array($key, $core, true) && substr($key, -1) !== '_') {
        ok(isset($en[$key]), "language key $key exists");
    }
}
foreach (array('active', 'grace', 'suspended', 'inactive', 'invalid', 'connection_error', 'unknown') as $s) {
    ok(isset($en['status_' . $s]), "status_$s exists");
}
foreach (array_keys(ProtesidePageMatcher::defaultPages()) as $g) {
    ok(isset($en['entry_' . $g]), "entry_$g exists");
}

// Packages contain an identical copy of lib/, bootstrapper/ and lang/
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/build.php') . ' --check 2>&1', $out, $code);
eq(0, $code, 'packages in sync with sources: ' . implode(' ', $out));

// Versions agree
$install = json_decode(file_get_contents($root . '/opencart-4/proteside/install.json'), true);
eq(ProtesideService::VERSION, $install['version'], 'install.json version matches ProtesideService::VERSION');
ok(strpos(file_get_contents($root . '/CHANGELOG.md'), '## ' . ProtesideService::VERSION) !== false, 'CHANGELOG has the current version');

// Event and cron routes point to methods that exist
$oc3 = file_get_contents($root . '/opencart-3/upload/catalog/controller/extension/module/proteside.php');
ok(strpos($oc3, 'public function injectHeader(&$route, &$args, &$output)') !== false, 'OC3 event handler signature');
$oc4 = file_get_contents($root . '/opencart-4/proteside/catalog/controller/module/proteside.php');
ok(strpos($oc4, 'public function injectHeader(string &$route = \'\', array &$args = [], string &$output = \'\'): void') !== false, 'OC4 event handler signature');
ok(strpos(file_get_contents($root . '/opencart-4/proteside/admin/controller/module/proteside.php'), "'extension/proteside/module/proteside.injectHeader'") !== false, 'OC4 event action uses the 4.0.2+ method separator');

// No file mentions other e-commerce platforms or internal planning documents
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = $file->getPathname();
    if (strpos($path, '/dist/') !== false || !preg_match('/\.(php|twig|md|json)$/', $path) || basename($path) === 'package_test.php') {
        continue;
    }
    $text = file_get_contents($path);
    foreach (array('WooCommerce', 'PrestaShop', 'Magento', 'Shopware', 'BigCommerce', 'Nuvemshop', 'VTEX', '_common', 'CONTRACT') as $word) {
        if (stripos($text, $word) !== false) {
            ok(false, substr($path, strlen($root) + 1) . " mentions $word");
        }
    }
}

done('package');
