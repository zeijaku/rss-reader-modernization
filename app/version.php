<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.21.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0';

/**
 * Cache key for public assets.
 * V1.22 keeps the formal application version at 1.21.0 while using checkpoint
 * asset keys so staged JavaScript/CSS is not served from an older browser cache.
 */
const APP_ASSET_REVISION = '1.22.0-d';
