<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.25.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.25.0';

/**
 * Cache key for public assets.
 * Formal release assets use the same immutable cache key as APP_VERSION.
 */
const APP_ASSET_REVISION = '1.25.0';

/**
 * V1.26-D: scoped cache key for the Information Board bootstrap chain.
 * memo-counter.js inherits this query to info-board.js / info-board-ticker.js,
 * so staged Information Board assets can be refreshed without invalidating
 * every long-lived Dashboard asset before the formal release version changes.
 */
const INFO_BOARD_ASSET_REVISION = '1.26.0-dev-d4';
