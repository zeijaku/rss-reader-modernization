<?php

declare(strict_types=1);

/**
 * Application and static asset versions.
 *
 * Do not derive these values from Git tags at runtime. They are the single
 * source of truth for rendered release/checkpoint labels and cache-busting.
 *
 * Release note:
 *  - Formal releases must use the same immutable key for APP_VERSION and
 *    APP_ASSET_REVISION.
 *  - Development checkpoints should also advance both values when they are
 *    distributed for browser/server verification so cached assets are not
 *    mixed across checkpoints.
 */
const APP_VERSION = '1.34.2-dev.1';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.34.2-dev.1';
const APP_ASSET_REVISION = '1.34.2-dev.1';

/**
 * V1.26-D: scoped cache key for the Information Board bootstrap chain.
 * Keep this pinned until those scoped assets change again.
 */
const INFO_BOARD_ASSET_REVISION = '1.26.0';
