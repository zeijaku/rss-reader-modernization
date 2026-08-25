<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.20.1';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.20.1';

/**
 * Cache key for public assets.
 * V1.21-C keeps the visible V1.20.1 release marker while using a new asset
 * revision so Production verification cannot reuse the earlier C mobile layer.
 */
const APP_ASSET_REVISION = '1.21-c3';
