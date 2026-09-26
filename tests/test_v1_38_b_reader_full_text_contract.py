#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

api = (ROOT / "app/api.php").read_text(encoding="utf-8")
content = (ROOT / "app/api/content.php").read_text(encoding="utf-8")
service = (ROOT / "app/reader/reader_full_text.php").read_text(encoding="utf-8")
modal = (ROOT / "app/view/dashboard_modals.php").read_text(encoding="utf-8")
js = (ROOT / "public/js/dashboard.js").read_text(encoding="utf-8")
http_fetch = (ROOT / "app/http_fetch.php").read_text(encoding="utf-8")
config = (ROOT / "app/common/common_conf.php").read_text(encoding="utf-8")

checks = [
    ("'feed.reader.full_text' => api_feed_reader_full_text" in api,
     "Full Text endpoint is registered"),
    ("function api_feed_reader_full_text" in content,
     "Full Text API action exists"),
    ("$readerResponse = api_feed_reader($userId, $input);" in content
     and "$articleUrl = is_string($reader['article_url']" in content,
     "Full Text URL is resolved from the owned Feed Reader payload"),
    ("client-supplied article URL" in content
     and "ReaderFullTextService::fromRuntimeConfiguration()->load($articleUrl)" in content,
     "client URL is not trusted for server-side fetch"),
    ("app_safe_http_fetch($sourceUrl)" in service,
     "Full Text reuses the shared safe outbound HTTP boundary"),
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
    ("apiRequest('feed.reader.full_text', context, 25000)" in js,
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
