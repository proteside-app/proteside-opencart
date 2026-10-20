<?php
/**
 * Proteside checkout protection for OpenCart - build.
 *
 *   php tools/build.php          sync lib/, bootstrapper/ and lang/ into both packages, then write dist/*.ocmod.zip
 *   php tools/build.php --check  fail (exit 1) if a package copy differs from its source; writes nothing
 *
 * Sources (edit these, never the copies):
 *   lib/*.php            -> <package library>/
 *   bootstrapper/*       -> <package library>/bootstrapper/
 *   lang/<code>.php      -> admin language file of each package
 *
 * Packages:
 *   opencart-3/upload/  -> dist/proteside-opencart-3.ocmod.zip  (OpenCart 3.0.x: Extensions > Installer)
 *   opencart-4/proteside/ -> dist/proteside.ocmod.zip           (OpenCart 4.0.2+: the file name must be proteside.ocmod.zip)
 */
$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

$languages = array('en-gb', 'pt-br', 'es-es');

$targets = array(
    'opencart-3' => array(
        'library' => $root . '/opencart-3/upload/system/library/proteside',
        'language' => $root . '/opencart-3/upload/admin/language/%s/extension/module/proteside.php',
    ),
    'opencart-4' => array(
        'library' => $root . '/opencart-4/proteside/system/library/proteside',
        'language' => $root . '/opencart-4/proteside/admin/language/%s/module/proteside.php',
    ),
);

/** @var array list of [source, destination] */
$copies = array();
foreach ($targets as $target) {
    foreach (glob($root . '/lib/*.php') as $file) {
        $copies[] = array($file, $target['library'] . '/' . basename($file));
    }
    foreach (glob($root . '/bootstrapper/*') as $file) {
        $copies[] = array($file, $target['library'] . '/bootstrapper/' . basename($file));
    }
    foreach ($languages as $code) {
        $copies[] = array($root . '/lang/' . $code . '.php', sprintf($target['language'], $code));
    }
}

$stale = array();
foreach ($copies as $pair) {
    list($src, $dst) = $pair;
    $same = is_file($dst) && hash_file('sha256', $src) === hash_file('sha256', $dst);
    if ($same) {
        continue;
    }
    if ($check) {
        $stale[] = substr($dst, strlen($root) + 1);
        continue;
    }
    if (!is_dir(dirname($dst))) {
        mkdir(dirname($dst), 0755, true);
    }
    copy($src, $dst);
    echo 'synced  ', substr($dst, strlen($root) + 1), "\n";
}

// Copies whose source no longer exists.
foreach ($targets as $target) {
    $existing = array_merge(glob($target['library'] . '/*.php'), glob($target['library'] . '/bootstrapper/*'));
    foreach ($existing as $file) {
        if (!in_array($file, array_column($copies, 1), true)) {
            if ($check) {
                $stale[] = substr($file, strlen($root) + 1) . ' (orphan)';
            } else {
                unlink($file);
                echo 'removed ', substr($file, strlen($root) + 1), "\n";
            }
        }
    }
}

if ($check) {
    if ($stale) {
        fwrite(STDERR, "Out of sync (run php tools/build.php):\n  " . implode("\n  ", $stale) . "\n");
        exit(1);
    }
    echo "Packages are in sync with lib/, bootstrapper/ and lang/.\n";
    exit(0);
}

/**
 * Zip a directory. Entries are sorted and timestamps fixed so the archive
 * only changes when a file changes.
 *
 * @param string $dir directory to pack
 * @param string $zipFile
 * @param string $prefix path prefix inside the archive ('' or 'upload/')
 */
function proteside_zip($dir, $zipFile, $prefix)
{
    if (is_file($zipFile)) {
        unlink($zipFile);
    }
    $files = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $name = $file->getFilename();
        if ($name === '.DS_Store' || strpos($name, '._') === 0) {
            continue;
        }
        $files[] = $file->getPathname();
    }
    sort($files);
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "Cannot write $zipFile\n");
        exit(1);
    }
    $dirs = array();
    foreach ($files as $path) {
        $rel = $prefix . str_replace('\\', '/', substr($path, strlen($dir) + 1));
        $parts = explode('/', $rel);
        array_pop($parts);
        $acc = '';
        foreach ($parts as $part) {
            $acc .= $part . '/';
            if (!isset($dirs[$acc])) {
                $dirs[$acc] = true;
                $zip->addEmptyDir($acc);
                $zip->setMtimeName($acc, 1791936000);
            }
        }
        $zip->addFile($path, $rel);
        $zip->setMtimeName($rel, 1791936000);
    }
    $zip->close();
    echo 'wrote   ', basename(dirname($zipFile)), '/', basename($zipFile), ' (', count($files), " files)\n";
}

if (!is_dir($root . '/dist')) {
    mkdir($root . '/dist', 0755, true);
}
proteside_zip($root . '/opencart-3/upload', $root . '/dist/proteside-opencart-3.ocmod.zip', 'upload/');
proteside_zip($root . '/opencart-4/proteside', $root . '/dist/proteside.ocmod.zip', '');
