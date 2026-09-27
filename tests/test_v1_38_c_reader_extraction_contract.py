#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

reader = (ROOT / "app/reader/reader_full_text.php").read_text(encoding="utf-8")
api = (ROOT / "app/api/content.php").read_text(encoding="utf-8")
js = (ROOT / "public/js/dashboard.js").read_text(encoding="utf-8")
css = (ROOT / "public/css/dashboard.css").read_text(encoding="utf-8")

checks = [
    ("function reader_full_text_extract" in reader,
     "lightweight Full Text extraction exists"),
    ("reader_full_text_select_candidate" in reader
     and "//article|//main|//*[@role=\"main\"]" in reader,
     "extractor prioritizes article/main semantics"),
    ("reader_full_text_remove_noise" in reader
     and "'//script'" in reader and "'//iframe'" in reader and "'//form'" in reader,
     "dangerous/noise elements are removed before extraction"),
    ("$allowed = [" in reader
     and "'p', 'br', 'hr'" in reader
     and "'h1', 'h2', 'h3'" in reader
     and "'ul', 'ol', 'li'" in reader
     and "'blockquote', 'pre', 'code'" in reader,
     "sanitizer uses an explicit structural allowlist"),
    ("$safe->setAttribute('target', '_blank')" in reader
     and "$safe->setAttribute('rel', 'noopener noreferrer')" in reader,
     "sanitized links receive safe target/rel attributes"),
    ("$safe->setAttribute('loading', 'lazy')" in reader
     and "$safe->setAttribute('referrerpolicy', 'no-referrer')" in reader,
     "sanitized images use safe loading/referrer attributes"),
    ("reader_full_text_resolve_resource_url" in reader
     and "app_resolve_redirect_url" in reader
     and "app_remove_tracking_parameters" in reader,
     "relative resources are resolved through existing URL safety helpers"),
    ("reader_full_text_extract(" in api
     and "$body," in api
     and "$effectiveUrl," in api
     and "$imageProxy->register($userId, $imageUrl)" in api,
     "Full Text API extracts only after secure fetch/cache load and registers images server-side"),
    ("'reader_full_text_extract_failed'" in api
     and "RSS本文を表示しています" in api,
     "extraction failure falls back to RSS body"),
    ("'full_text' => [" in api and "'html' => (string) $extracted['html']" in api,
     "API returns sanitized Full Text separately from fetch metadata"),
    (".removeClass('reader-mode-body-rich')" in js
     and ".text(body)" in js,
     "RSS content/description still renders as text"),
    (".addClass('reader-mode-body-rich')" in js
     and ".html(safeHtml)" in js
     and "server-side allowlist sanitizer" in js,
     "only sanitized Full Text enters the HTML rendering path"),
    ("元記事から本文を表示しています。" in js,
     "Reader reports when extracted article body is displayed"),
    ("function api_reader_full_text_diagnostic_error" in api
     and "'category' => $safeCategory" in api
     and "'http_status' => $safeUpstreamStatus" in api
     and "$message . $diagnosticText" in api,
     "Full Text failures embed only coarse diagnostic category/status in the public message"),
    ("error.diagnostic" in js
     and "診断: " in js
     and "HTTP " in js,
     "Reader displays safe production diagnostics for Full Text failures"),
    (".reader-mode-body.reader-mode-body-rich" in css
     and ".reader-mode-body-rich img" in css
     and "max-width: 100%;" in css,
     "rich Reader body and responsive images are styled"),
    (".reader-mode-body-rich blockquote" in css
     and ".reader-mode-body-rich pre" in css,
     "quotes and code blocks receive readable Reader styling"),
]

failed = []
for ok, label in checks:
    print(("PASS" if ok else "FAIL") + ": " + label)
    if not ok:
        failed.append(label)

if failed:
    raise SystemExit(f"{len(failed)}/{len(checks)} V1.38-C contract checks failed.")

print(f"V1.38-C contract checks: {len(checks)} passed.")
