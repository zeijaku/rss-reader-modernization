#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

api = (ROOT / "app/api.php").read_text(encoding="utf-8")
api_v1 = (ROOT / "public/api_v1.php").read_text(encoding="utf-8")
content = (ROOT / "app/api/content.php").read_text(encoding="utf-8")
service = (ROOT / "app/reader/reader_full_text.php").read_text(encoding="utf-8")
modal = (ROOT / "app/view/dashboard_modals.php").read_text(encoding="utf-8")
js = (ROOT / "public/js/dashboard.js").read_text(encoding="utf-8")
http_fetch = (ROOT / "app/http_fetch.php").read_text(encoding="utf-8")
config = (ROOT / "app/common/common_conf.php").read_text(encoding="utf-8")
feed_fetcher = (ROOT / "app/feed/feed_fetcher.php").read_text(encoding="utf-8")

checks = [
    ("'feed.reader.fulltext' => api_feed_reader_full_text" in api,
     "Full Text endpoint is registered"),
    (re.fullmatch(r"[a-z]+(?:\.[a-z]+)+", "feed.reader.fulltext") is not None
     and "preg_match('/^[a-z]+(?:\\.[a-z]+)+$/'" in api_v1,
     "Full Text action conforms to the public API action-name grammar"),
    ("feed.reader.full_text" not in api and "feed.reader.full_text" not in js,
     "legacy underscore Full Text action is not used"),

    ("function api_feed_reader_full_text" in content,
     "Full Text API action exists"),
    ("$readerResponse = api_feed_reader($userId, $input);" in content
     and "$articleUrl = is_string($reader['article_url']" in content,
     "Full Text URL is resolved from the owned Feed Reader payload"),
    ("client-supplied article URL" in content
     and "ReaderFullTextService::fromRuntimeConfiguration()->load($articleUrl)" in content,
     "client URL is not trusted for server-side fetch"),
    ("app_safe_http_fetch(" in service and "$sourceUrl," in service,
     "Full Text reuses the shared safe outbound HTTP boundary"),
    ("'accept' => 'text/html, application/xhtml+xml;q=0.9, */*;q=0.1'" in service,
     "Full Text uses HTML content negotiation"),
    ("'retry_public_ips' => true" in service and "retry_public_ips" in http_fetch,
     "Full Text may retry only across already validated public DNS answers"),
    ("APP_FEED_USER_AGENT" in config
     and "APP_READER_USER_AGENT" in config
     and "APP_HTTP_USER_AGENT" in config,
     "Feed and Reader user-agent settings retain the legacy HTTP fallback"),
    ("['user_agent' => (string) APP_FEED_USER_AGENT]" in feed_fetcher,
     "RSS/Atom fetch uses the dedicated Feed user agent"),
    ("'user_agent' => (string) APP_READER_USER_AGENT" in service,
     "Full Text fetch uses the dedicated Reader user agent"),
    ("$userAgent = $requestOptions['user_agent'] ?? APP_HTTP_USER_AGENT" in http_fetch,
     "shared safe fetch keeps APP_HTTP_USER_AGENT as compatibility fallback"),
    ("reader_full_text_normalize_html_utf8" in service
     and "reader_full_text_charset_from_content_type" in service
     and "reader_full_text_charset_from_html" in service,
     "Full Text normalizes declared legacy encodings to UTF-8 before extraction"),
    ("private const SCHEMA_VERSION = 2;" in service
     and "app_is_valid_utf8($body)" in service,
     "Reader cache schema only accepts normalized UTF-8 article bodies"),
    ("feed_health" not in service.lower(),
     "article fetch does not alter Feed Health"),
    ("reader_full_text_content_type_allowed" in service
     and "'text/html'" in service
     and "'application/xhtml+xml'" in service,
     "Full Text allowlists HTML response Content-Types"),
    ("APP_READER_FULL_TEXT_CACHE_DIR" in config
     and "APP_READER_FULL_TEXT_STALE_MAX_AGE_SECONDS" in config,
     "Reader Full Text uses bounded file cache settings"),
    ("body_base64" in service and "body_sha256" in service
     and "is_link($path)" in service,
     "Reader cache validates size/checksum and rejects cache symlinks"),
    ("'content_type' => $contentType" in http_fetch,
     "shared safe fetch exposes Content-Type metadata"),
    ('id="readerModeFullTextButton"' in modal
     and "全文を取得" in modal,
     "Reader Modal exposes an explicit Full Text button"),
    ("apiRequest('feed.reader.fulltext', context, 25000)" in js,
     "Full Text fetch is initiated by the dedicated on-demand action"),
    ("fetchReaderFullText($(this));" in js,
     "Full Text request is bound to button click"),
    ("apiRequest('feed.reader', context, 25000)" in js,
     "opening Reader still only loads RSS Reader content"),
    ("本文抽出は次の段階で反映します" in js,
     "Stage B does not pretend raw HTML has already been extracted"),
    ("readerFullTextErrorMessage" in js
     and "RSS本文を表示しています" in js,
     "Full Text failure explicitly preserves RSS fallback"),
]

failed = []
for ok, label in checks:
    print(("PASS" if ok else "FAIL") + ": " + label)
    if not ok:
        failed.append(label)

if failed:
    raise SystemExit(f"{len(failed)}/{len(checks)} V1.38-B contract checks failed.")

print(f"V1.38-B contract checks: {len(checks)} passed.")
