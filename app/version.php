<?php

declare(strict_types=1);

/**
 * Visible release marker for deployment verification.
 * Update these values for every distributed checkpoint/build.
 */
const APP_VERSION = '1.18.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.18.0';

/**
 * Cache key for public assets.
 * Stable releases normally align this with APP_VERSION; pre-Git cache-bust revisions may add a suffix.
 */
const APP_ASSET_REVISION = '1.18.0-r2';
