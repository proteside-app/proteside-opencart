<?php
/** Runs every *_test.php in its own PHP process and reports the totals. */
$failed = 0;
foreach (glob(__DIR__ . '/*_test.php') as $file) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file), $code);
    $failed += $code ? 1 : 0;
}
echo $failed ? "\n$failed suite(s) failed\n" : "\nAll suites passed\n";
exit($failed ? 1 : 0);
