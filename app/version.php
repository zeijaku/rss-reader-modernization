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
 * V1.27-E2 uses a development revision for the Drawer cache-busting hotfix.
 */
const APP_ASSET_REVISION = '1.27.0-dev-e2';

/**
 * V1.26-D: scoped cache key for the Information Board bootstrap chain.
 * V1.27-E2 does not change those assets, so keep the formal revision.
 */
const INFO_BOARD_ASSET_REVISION = '1.26.0';
