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
 */
const APP_ASSET_REVISION = '1.26.0';

/**
 * V1.26-D: scoped cache key for the Information Board bootstrap chain.
 * Keep it aligned with the formal release so Information Board and existing
 * Dashboard assets share the immutable V1.26.0 deployment boundary.
 */
const INFO_BOARD_ASSET_REVISION = '1.26.0';
