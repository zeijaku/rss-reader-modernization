<?php

declare(strict_types=1);

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/common/common_conf.php';

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

$errorLogDirectory = dirname((string) APP_ERROR_LOG_PATH);
if (is_dir($errorLogDirectory) && is_writable($errorLogDirectory)) {
    ini_set('error_log', (string) APP_ERROR_LOG_PATH);
}

set_exception_handler(static function (Throwable $exception): void {
    try {
        $reference = bin2hex(random_bytes(6));
    } catch (Throwable) {
        $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    }

    error_log(sprintf(
        'Unhandled application exception ref=%s [%s] at %s:%d: %s',
        $reference,
        $exception::class,
        $exception->getFile(),
        $exception->getLine(),
        $exception->getMessage()
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    if (APP_DEBUG) {
        echo "Application error [{$reference}]: {$exception->getMessage()}";
        return;
    }

    echo "An internal application error occurred. Reference: {$reference}";
});

require_once __DIR__ . '/common/common_db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/http_fetch.php';
require_once __DIR__ . '/feed/feed_source.php';
require_once __DIR__ . '/feed/feed_http_headers.php';
require_once __DIR__ . '/feed/feed_source_mapper.php';
require_once __DIR__ . '/feed/feed_transport_interface.php';
require_once __DIR__ . '/feed/feed_fetcher.php';
require_once __DIR__ . '/feed/feed_cache_entry.php';
require_once __DIR__ . '/feed/feed_cache_lock.php';
require_once __DIR__ . '/feed/feed_cache.php';
require_once __DIR__ . '/feed/item_identity.php';
require_once __DIR__ . '/feed/normalized_item.php';
require_once __DIR__ . '/feed/item_identity_resolver.php';
require_once __DIR__ . '/feed/feed_date_normalizer.php';
require_once __DIR__ . '/feed/feed_link_selector.php';
require_once __DIR__ . '/feed/feed_xml_helper.php';
require_once __DIR__ . '/feed/adapters/feed_adapter_interface.php';
require_once __DIR__ . '/feed/adapters/rss2_adapter.php';
require_once __DIR__ . '/feed/adapters/rss1_adapter.php';
require_once __DIR__ . '/feed/adapters/atom_adapter.php';
require_once __DIR__ . '/feed/feed_parser.php';
require_once __DIR__ . '/feed/feed_fetch_service.php';
require_once __DIR__ . '/common/common_func.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/login_throttle.php';
require_once __DIR__ . '/auth.php';
