<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.16.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.16.0';

/**
 * Cache key for public assets while a future release is being verified.
 * Keep the visible APP_VERSION unchanged until the release gate.
 */
const APP_ASSET_REVISION = '1.17-f-r1';
