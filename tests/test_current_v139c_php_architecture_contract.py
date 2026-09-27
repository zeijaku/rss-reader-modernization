#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


content_facade = read("app/api/content.php")
content_actions = read("app/api/content/content_actions.php")
stock_actions = read("app/api/content/stock_actions.php")
feed_actions = read("app/api/content/feed_actions.php")
reader_actions = read("app/api/content/reader_actions.php")
reader_facade = read("app/reader/reader_full_text.php")
reader_request = read("app/reader/full_text/request.php")
reader_charset = read("app/reader/full_text/charset.php")
reader_extraction = read("app/reader/full_text/extraction.php")
reader_cache = read("app/reader/full_text/cache.php")
reader_service = read("app/reader/full_text/service.php")
api_dispatch = read("app/api.php")

checks = []


def check(condition: bool, label: str) -> None:
    checks.append(bool(condition))
    print(("PASS" if condition else "FAIL") + ": " + label)


for module in [
    "content/content_actions.php",
    "content/stock_actions.php",
    "content/feed_actions.php",
    "content/reader_actions.php",
]:
    check(module in content_facade, f"Content API facade loads {module}")
check("function api_" not in content_facade, "Content API facade contains no concrete action implementation")

for function_name in ["api_content_create", "api_content_update", "api_content_delete"]:
    check(f"function {function_name}" in content_actions, f"Content module owns {function_name}")
for function_name in [
    "api_stock_create",
    "api_stock_delete",
    "api_stock_tag_attach",
    "api_stock_tag_detach",
    "api_stock_tag_rename",
    "api_stock_tag_delete",
]:
    check(f"function {function_name}" in stock_actions, f"Stock module owns {function_name}")
for function_name in [
    "api_feed_keyword_create",
    "api_feed_keyword_delete",
    "api_feed_fetch",
    "api_feed_reader_payload",
    "api_feed_reader",
    "api_feed_new_clear",
]:
    check(f"function {function_name}" in feed_actions, f"Feed module owns {function_name}")
for function_name in ["api_reader_full_text_diagnostic_error", "api_feed_reader_full_text"]:
    check(f"function {function_name}" in reader_actions, f"Reader API module owns {function_name}")

check("dashboard_widget_create_feed($userId" in content_actions, "Content creation keeps authenticated owner scope")
check("info_dbsave($userId" in stock_actions, "Stock creation keeps authenticated owner scope")
check("find_owned_active_content($userId, $contentId)" in feed_actions, "Feed/Reader lookup keeps authenticated owner scope")
check("$readerResponse = api_feed_reader($userId, $input);" in reader_actions, "Full Text resolves article URL through owned Reader payload")
check("$imageProxy->register($userId, $imageUrl)" in reader_actions, "Full Text image registration remains owner-bound")

for module in [
    "full_text/request.php",
    "full_text/charset.php",
    "full_text/extraction.php",
    "full_text/cache.php",
    "full_text/service.php",
]:
    check(module in reader_facade, f"Reader Full Text facade loads {module}")
check("function reader_full_text_" not in reader_facade and "class ReaderFullText" not in reader_facade,
      "Reader Full Text facade contains no concrete implementation")

check("app_validate_external_link" in reader_request and "app_validate_feed_url" in reader_request,
      "Reader request module preserves strict URL validation")
check("'text/html'" in reader_request and "'application/xhtml+xml'" in reader_request,
      "Reader request module preserves HTML Content-Type allowlist")
check("reader_full_text_detect_charset" in reader_charset and "reader_full_text_normalize_html_utf8" in reader_charset,
      "Reader charset normalization remains isolated and explicit")
check("reader_full_text_remove_noise" in reader_extraction and "reader_full_text_sanitize_node" in reader_extraction,
      "Reader extraction keeps noise removal and allowlist sanitizer")
check("app_resolve_redirect_url" in reader_extraction and "app_remove_tracking_parameters" in reader_extraction,
      "Reader resource URL resolution keeps existing safe URL helpers")
check("target', '_blank'" in reader_extraction and "noopener noreferrer" in reader_extraction,
      "Reader links retain opener isolation")
check("referrerpolicy', 'no-referrer'" in reader_extraction and "loading', 'lazy'" in reader_extraction,
      "Reader images retain privacy/loading restrictions")
check("private const SCHEMA_VERSION = 2;" in reader_cache and "body_sha256" in reader_cache,
      "Reader cache retains schema/checksum validation")
check("is_link($path)" in reader_cache and "app_is_valid_utf8($body)" in reader_cache,
      "Reader cache rejects symlinks and non-UTF-8 bodies")
check("app_safe_http_fetch(" in reader_service and "'retry_public_ips' => true" in reader_service,
      "Reader service still crosses the shared safe outbound HTTP boundary")
check("'user_agent' => (string) APP_READER_USER_AGENT" in reader_service,
      "Reader service retains dedicated fixed User-Agent")
check("reader_full_text_content_type_allowed" in reader_service
      and "reader_full_text_normalize_html_utf8" in reader_service,
      "Reader service validates type and normalizes charset before cache/write")

for action in ["feed.fetch", "feed.reader", "feed.reader.fulltext", "feed.new.clear"]:
    check(f"'{action}'" in api_dispatch, f"Public API action remains registered: {action}")

failed = len(checks) - sum(checks)
print(f"RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0")
raise SystemExit(1 if failed else 0)
