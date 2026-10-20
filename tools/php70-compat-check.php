<?php
/**
 * Proteside checkout protection for OpenCart - syntax floor check.
 *
 *   php tools/php70-compat-check.php [file ...]   (default: the OpenCart 3.0 package and lib/)
 *
 * The OpenCart 3.0 package (and the shared library it bundles) must parse on
 * PHP 7.0. This scans their tokens for syntax introduced later: return and
 * nullable types (7.1), typed properties and arrow functions (7.4), match,
 * nullsafe operator, attributes and named arguments (8.0), enums/readonly (8.1).
 * The OpenCart 4 controllers are excluded: OpenCart 4 requires PHP 8.
 */
$root = dirname(__DIR__);
$files = array_merge(glob($root . '/lib/*.php'), array(
    $root . '/opencart-3/upload/admin/controller/extension/module/proteside.php',
    $root . '/opencart-3/upload/catalog/controller/extension/module/proteside.php',
), glob($root . '/lang/*.php'));
if (count($argv) > 1) {
    $files = array_map('realpath', array_slice($argv, 1));
}

$problems = array();
foreach ($files as $file) {
    $code = file_get_contents($file);
    $tokens = token_get_all($code);
    $rel = strpos($file, $root . '/') === 0 ? substr($file, strlen($root) + 1) : $file;
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        $id = is_array($t) ? $t[0] : null;
        $line = is_array($t) ? $t[2] : 0;
        foreach (array('T_FN' => 'arrow function (7.4)', 'T_MATCH' => 'match (8.0)', 'T_NULLSAFE_OBJECT_OPERATOR' => '?-> (8.0)',
            'T_COALESCE_EQUAL' => '??= (7.4)', 'T_ATTRIBUTE' => 'attribute (8.0)', 'T_ENUM' => 'enum (8.1)', 'T_READONLY' => 'readonly (8.1)') as $name => $label) {
            if (defined($name) && $id === constant($name)) {
                $problems[] = "$rel:$line $label";
            }
        }
        // Typed property: visibility followed by a type name and then a variable.
        if (in_array($id, array(T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR), true)) {
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_STATIC), true)) {
                $j++;
            }
            if ($j < $n && (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_STRING, T_ARRAY, T_CALLABLE), true) || $tokens[$j] === '?')) {
                $k = $j + 1;
                while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                    $k++;
                }
                if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_VARIABLE) {
                    $problems[] = "$rel:$line typed property (7.4)";
                }
            }
        }
        // Return type or nullable parameter type on a function declaration.
        if ($id === T_FUNCTION) {
            $depth = 0;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($tokens[$j] === '(') {
                    $depth++;
                } elseif ($tokens[$j] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($tokens[$j] === '?' && $depth === 1) {
                    $problems[] = "$rel:$line nullable parameter type (7.1)";
                }
            }
            for ($k = $j + 1; $k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE; $k++) {
            }
            if ($k < $n && $tokens[$k] === ':') {
                $problems[] = "$rel:$line return type (7.0 allows scalar ones, but this code base avoids them for 7.0/7.1 parity)";
            }
        }
    }
    // Named arguments: identifier followed by a colon inside a call, e.g. foo(bar: 1).
    if (preg_match_all('/[(,]\s*[a-z_][a-z0-9_]*\s*:(?!:)\s*[^\s)]/i', preg_replace("/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"|\/\/[^\n]*|\/\*.*?\*\//s", "''", $code), $m)) {
        foreach ($m[0] as $hit) {
            if (!preg_match('/\?\s*$/', $hit)) {
                $problems[] = "$rel possible named argument: " . trim($hit);
            }
        }
    }
}

if ($problems) {
    echo implode("\n", array_unique($problems)), "\n";
    exit(1);
}
printf("%d files parse with PHP 7.0 syntax.\n", count($files));
