#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

api = (ROOT / "app/api.php").read_text(encoding="utf-8")
content_api = (ROOT / "app/api/content.php").read_text(encoding="utf-8")
modals = (ROOT / "app/view/dashboard_modals.php").read_text(encoding="utf-8")
js = (ROOT / "public/js/dashboard.js").read_text(encoding="utf-8")
css = (ROOT / "public/css/dashboard.css").read_text(encoding="utf-8")
index = (ROOT / "public/index.php").read_text(encoding="utf-8")

checks = []

def check(condition: bool, label: str) -> None:
    checks.append((condition, label))
    print(("PASS" if condition else "FAIL") + ": " + label)

check("'feed.reader' => api_feed_reader(" in api, "feed.reader is registered in the authenticated API dispatcher")
check("function api_feed_reader_payload(" in content_api, "Reader payload builder exists")
check("function api_feed_reader(" in content_api, "Reader endpoint exists")
check("FeedFetchService::fromRuntimeConfiguration()->load($source)" in content_api, "Reader reuses the existing RSS fetch/cache service")
check("V1.38-Aは元記事を取得しない" in content_api, "V1.38-A explicitly keeps original-article fetch out of scope")
check("'full_text' => false" in content_api, "Reader payload does not claim Full Text fetch in Stage A")
check("id="readerModeModal"" in modals, "Reader uses the existing Bootstrap modal pattern")
check("modal-dialog-scrollable" in modals and "reader-mode-dialog" in modals, "Reader modal supports long-document scrolling")
check("id="readerModeTitle"" in modals and "id="readerModeSource"" in modals and "id="readerModeDate"" in modals, "Reader exposes title, source, and published date")
check("id="readerModeBody"" in modals and "tabindex="0"" in modals, "Reader article body is keyboard focusable")
check("id="readerModeOriginalLink"" in modals and 'rel="noopener noreferrer"' in modals, "Original article link is isolated from the opener")
check("article-action-reader" in index, "Article Actions exposes Reader Mode for RSS articles")
check("function openReaderMode()" in js and "apiRequest('feed.reader'" in js, "Reader UI loads article data on demand")
check("if (!/^\\d+$/.test(contentId)" in js, "Reader accepts numeric content IDs with the standard digit regex")
check("if (!/^\\\\d+$/.test(contentId)" not in js, "Reader does not use an over-escaped content ID regex")
check("$('#readerModeBody')" in js and ".text(body)" in js, "Reader renders body as text rather than injecting HTML")
check("safeFeedLink(payload.article_url)" in js, "Reader validates the browser-side original article link")
check(".reader-mode-body" in css and "max-width: 52rem" in css, "Reader body keeps a readable PC line width")
check("@media (max-width: 575.98px)" in css and ".reader-mode-dialog" in css, "Reader has Smartphone-specific layout rules")
check("min-width: 44px" in css and "min-height: 44px" in css, "Reader controls preserve 44px touch targets")
check("@media (prefers-reduced-motion: reduce)" in css, "Reader honors reduced-motion preference")
check("var(--bs-body-bg" in css and "var(--bs-body-color" in css, "Reader follows Bootstrap light/dark theme variables")

failed = [label for ok, label in checks if not ok]
if failed:
    raise SystemExit(f"{len(failed)}/{len(checks)} V1.38-A Reader UI contract checks failed.")

print(f"V1.38-A Reader UI contract checks: {len(checks)} passed.")
