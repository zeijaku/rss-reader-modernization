<?php

declare(strict_types=1);

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/asset.php';
require_once __DIR__ . '/response_cache.php';
require_once __DIR__ . '/error_response.php';

// Register the minimal fallback before loading runtime configuration so a bad
// private config file can still return the common 500 response.
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

    $jsonResponse = defined('APP_RESPONSE_FORMAT') && APP_RESPONSE_FORMAT === 'json';
    if ($jsonResponse) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            app_send_no_store_headers();
        }
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'internal_error',
                'message' => 'Internal server error. Reference: ' . $reference,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    app_render_error_page(500, $reference);
});

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

require_once __DIR__ . '/common/common_db.php';
require_once __DIR__ . '/validation.php';
require_once dirname(__DIR__) . '/config/common_feeds.php';
require_once __DIR__ . '/dashboard_widget.php';
require_once __DIR__ . '/information_widget.php';
require_once __DIR__ . '/links.php';
require_once __DIR__ . '/stock_tag.php';
require_once __DIR__ . '/feed_keyword.php';
require_once __DIR__ . '/mini_game.php';
require_once __DIR__ . '/search_feed.php';
require_once __DIR__ . '/calendar.php';
require_once __DIR__ . '/url_normalizer.php';
require_once __DIR__ . '/feed/feed_retry.php';
require_once __DIR__ . '/http_fetch.php';
require_once __DIR__ . '/weather.php';
require_once __DIR__ . '/sun_moon.php';
require_once __DIR__ . '/air_quality.php';
require_once __DIR__ . '/earthquake.php';
require_once __DIR__ . '/holiday.php';
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
require_once __DIR__ . '/feed/feed_item_state.php';
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
require_once __DIR__ . '/remember_token.php';
require_once __DIR__ . '/persistent_login.php';
require_once __DIR__ . '/account_settings.php';
