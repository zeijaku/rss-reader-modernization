<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.28.0-dev.6';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.28.0-dev.6';

/**
 * Cache key for public assets.
 * Formal release assets use the same immutable cache key as APP_VERSION.
 * V1.28-G uses the final integration checkpoint revision so current-following
 * assets do not reuse earlier V1.28 browser cache entries.
 */
const APP_ASSET_REVISION = '1.28.0-dev.6';

/**
 * V1.26-D: scoped cache key for the Information Board bootstrap chain.
 * V1.28 does not change those V1.26 Information Board assets, so keep their scoped formal revision.
 */
const INFO_BOARD_ASSET_REVISION = '1.26.0';
