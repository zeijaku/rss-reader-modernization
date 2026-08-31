<?php

declare(strict_types=1);

function remote_path_has_control_characters(string $value): bool
{
    return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
}

function remote_path_is_utf8(string $value): bool
{
    return function_exists('mb_check_encoding')
        ? mb_check_encoding($value, 'UTF-8')
        : preg_match('//u', $value) === 1;
}

/** @return list<string>|null */
function remote_path_segments(string $value): ?array
{
    if ($value === '' || strlen($value) > 2048 || str_contains($value, "\0") || str_contains($value, '\\')) {
        return null;
    }
    if (!remote_path_is_utf8($value) || remote_path_has_control_characters($value)) {
        return null;
    }

    $segments = [];
    foreach (explode('/', $value) as $segment) {
        if ($segment === '') {
            continue;
        }
        if ($segment === '.' || $segment === '..' || strlen($segment) > 255) {
            return null;
        }
        $segments[] = $segment;
    }
    return $segments;
}

function remote_path_normalize_base(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        $value = '/';
    }
    if (!str_starts_with($value, '/')) {
        return null;
    }
    $segments = remote_path_segments($value);
    if ($segments === null) {
        return null;
    }
    return $segments === [] ? '/' : '/' . implode('/', $segments);
}

/**
 * Browser/API paths are always relative to the connection base path.
 * The canonical root is '/'. Absolute remote filesystem paths are never accepted here.
 */
function remote_path_normalize_relative(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return '/';
    }
    if (!str_starts_with($value, '/')) {
        $value = '/' . $value;
    }
    $segments = remote_path_segments($value);
    if ($segments === null) {
        return null;
    }
    return $segments === [] ? '/' : '/' . implode('/', $segments);
}

function remote_path_join(string $basePath, string $relativePath): string
{
    $base = remote_path_normalize_base($basePath);
    $relative = remote_path_normalize_relative($relativePath);
    if ($base === null || $relative === null) {
        throw new AppRemoteValidationException('invalid_path');
    }
    if ($relative === '/') {
        return $base;
    }
    return $base === '/' ? $relative : $base . $relative;
}

function remote_path_parent(string $relativePath): string
{
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null || $path === '/') {
        return '/';
    }
    $segments = explode('/', ltrim($path, '/'));
    array_pop($segments);
    return $segments === [] ? '/' : '/' . implode('/', $segments);
}

function remote_path_basename(string $relativePath): string
{
    $path = remote_path_normalize_relative($relativePath);
    if ($path === null || $path === '/') {
        return '';
    }
    $segments = explode('/', ltrim($path, '/'));
    return (string) end($segments);
}

function remote_path_child(string $relativeDirectory, string $name): ?string
{
    $directory = remote_path_normalize_relative($relativeDirectory);
    if ($directory === null || $name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
        return null;
    }
    $segments = remote_path_segments('/' . $name);
    if ($segments === null || count($segments) !== 1) {
        return null;
    }
    return $directory === '/' ? '/' . $name : $directory . '/' . $name;
}

function remote_path_url_encode(string $absolutePath, bool $directory = false): string
{
    $path = remote_path_normalize_base($absolutePath);
    if ($path === null) {
        throw new AppRemoteValidationException('invalid_path');
    }
    $encoded = $path === '/'
        ? '/'
        : '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    if ($directory && !str_ends_with($encoded, '/')) {
        $encoded .= '/';
    }
    return $encoded;
}
