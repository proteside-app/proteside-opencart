<?php
require __DIR__ . '/bootstrap.php';

$bootPath = dirname(__DIR__) . '/bootstrapper/bootstrapper.min.js';
$boot = file_get_contents($bootPath);
$s = ProtesideSnippet::fromFile($bootPath);

// Key format
ok(ProtesideSnippet::isValidKey(KEY), 'live key is valid');
ok(ProtesideSnippet::isValidKey('pk_test_' . str_repeat('0', 32)), 'test key is valid');
ok(!ProtesideSnippet::isValidKey('pk_live_' . str_repeat('0', 31)), '31 hex chars is invalid');
ok(!ProtesideSnippet::isValidKey('pk_live_' . str_repeat('A', 32)), 'uppercase hex is invalid');
ok(!ProtesideSnippet::isValidKey('ps_live_' . str_repeat('0', 32)), 'API token prefix is invalid');
ok(!ProtesideSnippet::isValidKey(KEY . "\n"), 'trailing newline is invalid');
ok(!ProtesideSnippet::isValidKey(null), 'null is invalid');

// Bundled bootstrapper
ok($s->hasBootstrapper(), 'bundled bootstrapper is present');
eq('1.1.3', trim(file_get_contents(dirname(__DIR__) . '/bootstrapper/VERSION')), 'bootstrapper VERSION is 1.1.3');
eq('sha256-t2ypUgwBPt6N82k2gXq5D5ZHIdxOXxhj6ZISJatVbg8=', ProtesideSnippet::cspHash($boot), 'published file hash');
ok(!ProtesideSnippet::fromFile('/nonexistent')->hasBootstrapper(), 'missing bootstrapper detected');

// Inline JS without rules is the bootstrapper byte-for-byte
$js = $s->inlineJs();
eq($boot, $js, 'no rules: inline JS equals bootstrapper.min.js');
eq(ProtesideSnippet::cspHash($boot), ProtesideSnippet::cspHash($js), 'no rules: CSP hash equals the file hash');
ok(strpos($js, '"use strict";') === 0, 'starts with "use strict";');
ok(strpos($js, 'window.__PROTE_INLINE__=') === false, 'no rules: no inline assignment');

// Inline JS with rules
$rules = ProtesideSnippet::filterRules(array(
    array('type' => 'block', 'target' => 'domain', 'value' => 'evil.example'),
    array('type' => 'allow', 'target' => 'src', 'value' => 'https://ok.example/a.js'),
    array('type' => 'block', 'target' => 'hash', 'value' => 'sha256-abc'),
    array('type' => 'monitor', 'target' => 'domain', 'value' => 'x.example'),
    array('type' => 'block', 'target' => 'regex', 'value' => '.*'),
    array('type' => 'block', 'target' => 'domain', 'value' => ''),
    array('type' => 'block', 'target' => 'domain', 'value' => 42),
    'garbage',
));
eq(3, count($rules), 'filterRules keeps only block/allow on domain/src/hash with string value');
$gtm = ProtesideSnippet::filterGtm(array('GTM-ABC123', 'GTM-ABC123', 'gtm-abc123', 'GTM-AB', 'UA-1234', 'GTM-ABCDEFGHIJK', 7));
eq(array('GTM-ABC123'), $gtm, 'filterGtm keeps unique well-formed IDs');
eq(array(), ProtesideSnippet::filterRules('x'), 'filterRules on non-array');

$js = $s->inlineJs($rules, $gtm);
ok(strpos($js, '"use strict";window.__PROTE_INLINE__={') === 0, 'with rules: starts with "use strict";window.__PROTE_INLINE__={');
eq(1, substr_count($js, '"use strict";'), 'directive appears once');
ok(substr($js, -strlen($boot) + strlen('"use strict";')) === substr($boot, strlen('"use strict";')), 'rest of bootstrapper follows the assignment unchanged');
preg_match('/window\.__PROTE_INLINE__=(\{.*?\});var /', $js, $m);
$decoded = isset($m[1]) ? json_decode($m[1], true) : null;
eq(array('rules' => $rules, 'gtm' => $gtm), $decoded, 'inline payload is strict JSON with rules and gtm');
$only = $s->inlineJs(array(), array('GTM-ZZZZ99'));
ok(strpos($only, '"use strict";window.__PROTE_INLINE__={"rules":[],"gtm":["GTM-ZZZZ99"]};') === 0, 'GTM only still emits the assignment');

// No way to break out of the script tag
$evil = $s->inlineJs(array(array('type' => 'block', 'target' => 'src', 'value' => 'https://x.example/</script><script>alert(1)</script>')), array());
ok(stripos($evil, '</script') === false, 'rules cannot close the script tag');
ok(strpos($evil, '\u003C/script\u003E') !== false, '< and > are escaped as \u003C / \u003E');

// shield.js tag
eq('https://cdn.proteside.com/v1/shield.js', ProtesideSnippet::shieldSrc('stable'), 'stable URL');
eq('https://cdn.proteside.com/v1/latest/shield.js', ProtesideSnippet::shieldSrc('latest'), 'latest URL');
eq('https://cdn.proteside.com/v1/shield.js', ProtesideSnippet::shieldSrc('beta'), 'unknown channel falls back to stable');
eq('https://cdn2.proteside.com/v1/shield.js', ProtesideSnippet::shieldSrc('stable', 'https://cdn2.proteside.com/v1/'), 'cdnBase from status is honoured');
eq('https://cdn.proteside.com/v1/shield.js', ProtesideSnippet::shieldSrc('stable', 'https://evil.example/v1'), 'foreign cdnBase is ignored');
eq('https://cdn.proteside.com/v1/shield.js', ProtesideSnippet::shieldSrc('stable', 'http://cdn.proteside.com/v1'), 'non-https cdnBase is ignored');
eq('https://cdn.proteside.com/v1/shield.js', ProtesideSnippet::shieldSrc('stable', 'https://cdn.proteside.com.evil.example/v1'), 'look-alike host is ignored');

$tag = ProtesideSnippet::shieldTag(KEY, 'stable', null, 0);
eq('<script async src="https://cdn.proteside.com/v1/shield.js" data-key="' . KEY . '" data-api="https://app.proteside.com/api/sdk"></script>', $tag, 'shield tag, exact');
ok(strpos(ProtesideSnippet::shieldTag(KEY, 'stable', null, 30), ' data-calibration="30"') !== false, 'calibration 30 emitted');
ok(strpos(ProtesideSnippet::shieldTag(KEY, 'stable', null, 9), 'data-calibration') === false, 'calibration 9 omitted');
ok(strpos(ProtesideSnippet::shieldTag(KEY, 'stable', null, 121), 'data-calibration') === false, 'calibration 121 omitted');
preg_match_all('/\s(data-[a-z-]+)=/', ProtesideSnippet::shieldTag(KEY, 'latest', null, 60), $attrs);
eq(array('data-key', 'data-api', 'data-calibration'), $attrs[1], 'only data-key, data-api, data-calibration');

// Full snippet
$html = $s->render(KEY, array('channel' => 'latest', 'rules' => $rules, 'gtm' => $gtm, 'calibration' => 20));
$start = strpos($html, ProtesideSnippet::START_MARKER);
$inline = strpos($html, '<script data-cfasync="false" data-no-optimize="1" data-nowprocket data-pagespeed-no-defer>"use strict";');
$shield = strpos($html, '<script async src="https://cdn.proteside.com/v1/latest/shield.js"');
$end = strpos($html, ProtesideSnippet::END_MARKER);
ok($start !== false && $inline > $start && $shield > $inline && $end > $shield, 'markers, inline bootstrapper, then async shield.js');
eq(2, substr_count($html, '<script'), 'exactly two script tags');
ok(strpos($html, 'data-safe-url') === false && strpos($html, 'data-mode') === false && strpos($html, 'data-channel') === false, 'no unsupported attributes');
ok(!preg_match('/<script[^>]*\s(defer|type="module")/', $html), 'bootstrapper is never deferred or a module');
ok(strpos($html, '<script src=') === false, 'no external bootstrapper');

// Injection after <head>
$snippet = $s->render(KEY);
$page = "<!DOCTYPE html>\n<html dir=\"ltr\" lang=\"en\">\n<head>\n<meta charset=\"UTF-8\" />\n<title>x</title>\n<script src=\"jquery.js\"></script>\n</head><body></body></html>";
$out = ProtesideSnippet::injectAfterHead($page, $snippet);
ok(strpos($out, "<head>\n<meta charset=\"UTF-8\" />" . $snippet) !== false, 'injected right after <head> and its meta charset');
ok(strpos($out, ProtesideSnippet::START_MARKER) < strpos($out, 'jquery.js'), 'snippet before theme scripts');
eq($out, ProtesideSnippet::injectAfterHead($out, $snippet), 'idempotent');
$noCharset = "<html><head class=\"x\" data-a='1'><title>x</title><script src=\"a.js\"></script></head></html>";
ok(strpos(ProtesideSnippet::injectAfterHead($noCharset, $snippet), "<head class=\"x\" data-a='1'>" . $snippet . '<title>') !== false, 'head with attributes, no charset');
$httpEquiv = "<HEAD>\n  <META http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">\n<script>x()</script>";
ok(strpos(ProtesideSnippet::injectAfterHead($httpEquiv, $snippet), 'charset=utf-8">' . $snippet . "\n<script>") !== false, 'uppercase HEAD and http-equiv Content-Type');
$charsetLater = "<head><title>t</title><meta charset=\"utf-8\"><script></script>";
ok(strpos(ProtesideSnippet::injectAfterHead($charsetLater, $snippet), '<head>' . $snippet . '<title>') !== false, 'meta charset that is not first does not move the snippet');
eq('<header>no head</header>', ProtesideSnippet::injectAfterHead('<header>no head</header>', $snippet), '<header> is not <head>');
eq('', ProtesideSnippet::injectAfterHead('', $snippet), 'empty output unchanged');

// inspect()
$i = ProtesideSnippet::inspect($out);
ok($i['head_found'] && $i['injected'] && $i['first_in_head'] && $i['scripts_before'] === 0, 'inspect: first in head');
$late = "<head><script src=\"x.js\"></script><script>y()</script>" . $snippet . '</head>';
$i = ProtesideSnippet::inspect($late);
ok($i['injected'] && !$i['first_in_head'] && $i['scripts_before'] === 2, 'inspect: two scripts before');
$i = ProtesideSnippet::inspect('<head></head>');
ok($i['head_found'] && !$i['injected'], 'inspect: not injected');
$i = ProtesideSnippet::inspect('<div></div>');
ok(!$i['head_found'], 'inspect: no head');

done('snippet');
