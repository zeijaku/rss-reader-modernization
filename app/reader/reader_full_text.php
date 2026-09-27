<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/validation.php';
require_once dirname(__DIR__) . '/url_normalizer.php';
require_once dirname(__DIR__) . '/http_fetch.php';

/**
 * V1.39-C compatibility facade for Reader Full Text.
 *
 * Existing functions and classes remain available from this entry point while
 * implementation responsibilities live in smaller modules.
 */
require_once __DIR__ . '/full_text/request.php';
require_once __DIR__ . '/full_text/charset.php';
require_once __DIR__ . '/full_text/extraction.php';
require_once __DIR__ . '/full_text/cache.php';
require_once __DIR__ . '/full_text/service.php';
