<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [$root . '/app', $root . '/public', $root . '/tools'];
$files = [];
foreach ($paths as $path) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);

$issues = [];
foreach ($files as $file) {
    $tokens = token_get_all((string) file_get_contents($file));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $line = $token[2];
        $name = '(closure)';
        $j = $i + 1;
        for (; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_STRING) {
                $name = $t[1];
            }
            if ($t === '(') {
                break;
            }
        }
        if ($j >= $count || $tokens[$j] !== '(') {
            continue;
        }

        $params = [];
        $current = [];
        $depth = 1;
        $j++;
        for (; $j < $count && $depth > 0; $j++) {
            $t = $tokens[$j];
            if ($t === '(' || $t === '[' || $t === '{') {
                $depth++;
                $current[] = $t;
                continue;
            }
            if ($t === ')' || $t === ']' || $t === '}') {
                $depth--;
                if ($depth === 0) {
                    if ($current !== []) {
                        $params[] = $current;
                    }
                    break;
                }
                $current[] = $t;
                continue;
            }
            if ($t === ',' && $depth === 1) {
                $params[] = $current;
                $current = [];
                continue;
            }
            $current[] = $t;
        }

        $seenOptional = false;
        foreach ($params as $paramTokens) {
            $hasVariable = false;
            $hasDefault = false;
            $isVariadic = false;
            $nested = 0;
            foreach ($paramTokens as $pt) {
                if (is_array($pt) && $pt[0] === T_VARIABLE) {
                    $hasVariable = true;
                }
                if (defined('T_ELLIPSIS') && is_array($pt) && $pt[0] === T_ELLIPSIS) {
                    $isVariadic = true;
                }
                if ($pt === '(' || $pt === '[' || $pt === '{') {
                    $nested++;
                } elseif ($pt === ')' || $pt === ']' || $pt === '}') {
                    $nested--;
                } elseif ($pt === '=' && $nested === 0) {
                    $hasDefault = true;
                }
            }
            if (!$hasVariable || $isVariadic) {
                continue;
            }
            if ($hasDefault) {
                $seenOptional = true;
            } elseif ($seenOptional) {
                $issues[] = sprintf('%s:%d %s() has a required parameter after an optional parameter', $file, $line, $name);
                break;
            }
        }
    }
}

if ($issues !== []) {
    foreach ($issues as $issue) {
        echo 'FAIL: ' . $issue . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: no optional-before-required signatures in app/public/tools (' . count($files) . ' PHP files scanned)' . PHP_EOL;
