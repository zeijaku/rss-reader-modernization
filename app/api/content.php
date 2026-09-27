<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/feed_error.php';
require_once dirname(__DIR__) . '/reader/reader_full_text.php';
require_once dirname(__DIR__) . '/reader/reader_image_proxy.php';

/**
 * V1.39-C compatibility facade for Content / Stock / Feed / Reader API actions.
 *
 * Public action names and function signatures remain unchanged. The concrete
 * handlers are grouped by responsibility below so future changes can stay
 * within a narrower review and test boundary.
 */
require_once __DIR__ . '/content/content_actions.php';
require_once __DIR__ . '/content/stock_actions.php';
require_once __DIR__ . '/content/feed_actions.php';
require_once __DIR__ . '/content/reader_actions.php';
