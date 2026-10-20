<?php
/**
 * Proteside checkout protection for OpenCart - where is the snippet?
 *
 *   curl -s 'https://store.example/index.php?route=checkout/success' | php tools/inspect-head.php
 *
 * Prints whether the page has the Proteside snippet, whether it is the first
 * script after the opening head tag, the shield.js tag and the CSP hash of
 * the inline bootstrapper as served.
 */
require dirname(__DIR__) . '/lib/proteside.php';

$html = stream_get_contents(STDIN);
$i = ProtesideSnippet::inspect($html);
echo json_encode($i), "\n";
if ($i['injected'] && preg_match('#<!-- Proteside Start -->\s*<script([^>]*)>(.*?)</script>\s*(<script[^>]*></script>)\s*<!-- Proteside End -->#s', $html, $s)) {
    echo 'inline attributes:', $s[1], "\n";
    echo 'inline starts:     ', substr($s[2], 0, 60), "\n";
    echo 'shield:            ', $s[3], "\n";
    echo 'CSP hash:          ', ProtesideSnippet::cspHash($s[2]), "\n";
}
exit($i['injected'] && $i['first_in_head'] ? 0 : 1);
