<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.19.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.19.0';

/**
 * Cache key for public assets.
 * The final release uses its own revision so prior RC and V1.18 immutable caches are never reused.
 */
const APP_ASSET_REVISION = '1.19.0';
