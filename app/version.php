<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.26.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.26.0';

/**
 * Cache key for public assets.
 * Formal release assets use the same immutable cache key as APP_VERSION.
 * V1.27-C uses a development revision so production-side UI checks do not
 * reuse the formal V1.26.0 Dashboard CSS from browser/proxy caches.
 */
const APP_ASSET_REVISION = '1.27.0-dev-c1';

/**
 * V1.26-D: scoped cache key for the Information Board bootstrap chain.
 * V1.27-C changes Dashboard CSS only, so keep this chain on the formal
 * V1.26.0 revision instead of invalidating the independent bootstrap assets.
 */
const INFO_BOARD_ASSET_REVISION = '1.26.0';
