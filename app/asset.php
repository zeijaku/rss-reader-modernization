<?php

declare(strict_types=1);

/**
 * Build a versioned URL for a local public asset.
 *
 * Asset paths are intentionally limited to the public CSS / JavaScript
 * directories and the root favicon. External URLs, absolute paths, query
 * strings and path traversal are rejected before rendering.
 */
function app_asset_url(string $path): string
{
    $path = trim($path);

    if (str_contains($path, '\\')) {
        throw new InvalidArgumentException('Backslash asset paths are not allowed.');
    }

    if ($path === '' || str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
        throw new InvalidArgumentException('Invalid local asset path.');
    }

    if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1 || str_starts_with($path, '//')) {
        throw new InvalidArgumentException('External asset URLs are not allowed.');
    }

    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new InvalidArgumentException('Unsafe local asset path.');
        }
    }

    if (preg_match('/^[A-Za-z0-9._\/-]+$/', $path) !== 1) {
        throw new InvalidArgumentException('Unsupported local asset path.');
    }

    $allowed = $path === 'favicon.png'
        || str_starts_with($path, 'css/')
        || str_starts_with($path, 'js/');

    if (!$allowed) {
        throw new InvalidArgumentException('Asset path is outside the public asset allowlist.');
    }

    $revision = defined('APP_ASSET_REVISION') ? trim((string) APP_ASSET_REVISION) : '';
    if ($revision === '') {
        $revision = APP_VERSION;
    }

    $url = './' . $path . '?v=' . rawurlencode($revision);

    // V1.26-D: Information Board assets are staged through memo-counter.js.
    // Give only that bootstrap chain its own cache key while APP_VERSION and
    // the rest of the immutable Dashboard assets remain on the formal release.
    if ($path === 'js/memo-counter.js' && defined('INFO_BOARD_ASSET_REVISION')) {
        $infoBoardRevision = trim((string) INFO_BOARD_ASSET_REVISION);
        if ($infoBoardRevision !== '') {
            $url .= '&ib=' . rawurlencode($infoBoardRevision);
        }
    }

    return $url;
}
