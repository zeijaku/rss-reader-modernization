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
 * V1.22-A keeps the formal application version at 1.21.0 while using a
 * checkpoint asset key so the RSS management Drawer/JavaScript is not served
 * from the immutable V1.21.0 browser cache during Production verification.
 */
const APP_ASSET_REVISION = '1.22.0-a';
