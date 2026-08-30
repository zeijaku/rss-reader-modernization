from pathlib import Path

root = Path(__file__).resolve().parents[1]
module = (root / 'app/info_board.php').read_text(encoding='utf-8')
api_module = (root / 'app/api/info_board.php').read_text(encoding='utf-8')
public_api = (root / 'public/api_v1.php').read_text(encoding='utf-8')

checks = {
    'uses existing dashboard storage': "widget_type = 'search'" in module and 'dashboard_widget_encode_config($config)' in module,
    'private mode prevents normal Search confusion': "INFO_BOARD_MODE = 'info_board'" in module and 'info_board_config_from_storage' in module,
    'future source marker is explicit': "INFO_BOARD_SOURCE_TYPE = 'rss'" in module and "'source_type' => INFO_BOARD_SOURCE_TYPE" in module,
    'specific Feed lookup is owner scoped': 'content_id = :content_id AND content_owner = :owner AND content_flag = 0' in module,
    'specific Feed stores id not URL': "'feed_id' => $feedMode === 'specific' ? $feedId : null" in module,
    'all RSS reuses owned source catalog': 'search_feed_owned_sources($ownerId)' in module,
    'RSS fetch reuses secure service': 'FeedFetchService::fromRuntimeConfiguration()' in module,
    'Feed output passes safe payload boundary': 'api_safe_feed_payload($rawFeed, $effectiveUrl)' in module,
    'description falls back to content': "if ($summary === '')" in module and "item['content']" in module,
    'output reuses plain text sanitizer': 'api_feed_text($value, $maxLength)' in module,
    'no article scraping file access': 'file_get_contents(' not in module,
    'no direct curl transport': 'curl_' not in module,
    'API has exact create allowlist': "'widget.infoboard.create' => api_widget_info_board_create" in api_module,
    'API has exact update allowlist': "'widget.infoboard.update' => api_widget_info_board_update" in api_module,
    'API has exact delete allowlist': "'widget.infoboard.delete' => api_widget_info_board_delete" in api_module,
    'API has exact fetch allowlist': "'widget.infoboard.fetch' => api_widget_info_board_fetch" in api_module,
    'public endpoint loads module': "require_once dirname(__DIR__) . '/app/api/info_board.php';" in public_api,
    'public endpoint routes only infoboard group': "str_starts_with($action, 'widget.infoboard.')" in public_api,
    'public endpoint remains POST-only': "REQUEST_METHOD'] ?? 'GET') !== 'POST'" in public_api,
    'authentication remains before Info Board dispatch': public_api.index('app_session_user_id()') < public_api.index("str_starts_with($action, 'widget.infoboard.')"),
    'CSRF remains before Info Board dispatch': public_api.index('app_csrf_is_valid($csrfToken)') < public_api.index("str_starts_with($action, 'widget.infoboard.')"),
    'request cap remains before Info Board dispatch': public_api.index('APP_API_MAX_REQUEST_BYTES') < public_api.index("str_starts_with($action, 'widget.infoboard.')"),
    'action grammar accepts infoboard actions': "preg_match('/^[a-z]+(?:\\.[a-z]+)+$/', $action)" in public_api,
}

failed = []
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
    if not ok:
        failed.append(name)

if failed:
    raise SystemExit(f'{len(failed)}/{len(checks)} V1.26-B static checks failed')

print(f'All {len(checks)} V1.26-B static checks passed.')
