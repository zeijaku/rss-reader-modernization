#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

proxy = (ROOT / "app/reader/reader_image_proxy.php").read_text(encoding="utf-8")
reader = (ROOT / "app/reader/reader_full_text.php").read_text(encoding="utf-8")
content = "\n".join((ROOT / path).read_text(encoding="utf-8") for path in [
    "app/api/content.php",
    "app/api/content/content_actions.php",
    "app/api/content/stock_actions.php",
    "app/api/content/feed_actions.php",
    "app/api/content/reader_actions.php",
])
endpoint = (ROOT / "public/reader_image.php").read_text(encoding="utf-8")
http_fetch = (ROOT / "app/http_fetch.php").read_text(encoding="utf-8")
config = (ROOT / "app/common/common_conf.php").read_text(encoding="utf-8")
public_htaccess = (ROOT / "public/.htaccess").read_text(encoding="utf-8")

checks = [
    (
        "reader_image_proxy_token_for" in proxy
        and "hash_hmac('sha256'" in proxy
        and '"reader-image-v1\\0" . $userId . "\\0" . $url' in proxy
        and "INI_HASH_KEY" in proxy,
        "opaque image token is HMAC-bound to authenticated user and source URL",
    ),
    (
        "'source_url' => $url" in proxy
        and "reader-image-map-v1-" in proxy
        and "reader_image.php?id=" in reader,
        "source URL stays in private server registry while Reader receives only opaque id",
    ),
    (
        "$_GET['id']" in endpoint
        and "$_GET['url']" not in endpoint
        and "$_POST" not in endpoint,
        "public image endpoint never accepts a client-supplied source URL",
    ),
    (
        "reader_image\\.php$" in public_htaccess
        and "Reader image proxy is an explicit authenticated, token-only binary endpoint" in public_htaccess,
        "Apache public endpoint matrix explicitly permits reader_image.php",
    ),
    (
        "app_session_start();" in endpoint
        and "$userId = app_session_user_id();" in endpoint
        and "app_session_release();" in endpoint,
        "image endpoint requires authenticated session and releases session lock before outbound I/O",
    ),
    (
        "($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'" in endpoint
        and "header('Allow: GET')" in endpoint,
        "image endpoint is read-only GET",
    ),
    (
        "Cross-Origin-Resource-Policy: same-origin" in endpoint
        and "X-Content-Type-Options: nosniff" in endpoint
        and "Referrer-Policy: no-referrer" in endpoint
        and "app_send_private_no_store_headers()" in endpoint,
        "image endpoint uses same-origin/no-sniff/no-referrer/private response headers",
    ),
    (
        "app_safe_http_fetch(" in proxy
        and "'retry_public_ips' => true" in proxy
        and "'user_agent' => (string) APP_READER_USER_AGENT" in proxy,
        "image fetch reuses shared SSRF/TLS/redirect/DNS-pinning boundary and Reader UA",
    ),
    (
        "'max_bytes' => $this->maxBodyBytes" in proxy
        and "$maxBytes = $requestOptions['max_bytes'] ?? APP_HTTP_MAX_BYTES" in http_fetch
        and "min((int) APP_HTTP_MAX_BYTES, $maxBytes)" in http_fetch,
        "per-image response limit cannot exceed the existing global safe-fetch cap",
    ),
    (
        "'image/jpeg'" in proxy
        and "'image/png'" in proxy
        and "'image/webp'" in proxy
        and "'image/avif'" in proxy
        and "'image/svg+xml'" not in proxy,
        "proxy allowlists raster image MIME types and excludes SVG",
    ),
    (
        "reader_image_proxy_body_matches_type" in proxy
        and "\\xFF\\xD8\\xFF" in proxy
        and "\\x89PNG" in proxy
        and "'GIF87a'" in proxy
        and "'WEBP'" in proxy,
        "proxy validates file signatures instead of trusting Content-Type alone",
    ),
    (
        "is_link($path)" in proxy
        and "is_link($metaPath)" in proxy
        and "is_link($bodyPath)" in proxy
        and "body_sha256" in proxy
        and "hash_equals($sha256, hash('sha256', $body))" in proxy,
        "private registry/cache rejects symlinks and validates cached image checksum",
    ),
    (
        proxy.count("catch (Throwable)") >= 4
        and "json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)" in proxy
        and "json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)" in proxy,
        "image registry/cache metadata encoding failures are contained without exposing remote fallback",
    ),
    (
        "function reader_full_text_extract(string $html, string $effectiveUrl, ?callable $imageTokenMapper = null)" in reader
        and "'reader_image.php?id=' . rawurlencode($imageToken)" in reader,
        "Reader extraction supports same-origin image rewriting without changing article links",
    ),
    (
        "Privacy fail-safe" in reader
        and "return null;" in reader,
        "failed image proxy registration drops the image rather than restoring remote src",
    ),
    (
        "ReaderImageProxyService::fromRuntimeConfiguration()" in content
        and "$imageProxy->register($userId, $imageUrl)" in content,
        "Full Text API registers image sources under authenticated user identity",
    ),
    (
        "APP_READER_IMAGE_PROXY_ENABLED" in config
        and "APP_READER_IMAGE_CACHE_ENABLED" in config
        and "APP_READER_IMAGE_CACHE_DIR" in config
        and "/var/cache/reader-images" in config,
        "Reader image proxy/cache is configurable and stored outside public by default",
    ),
    (
        "min(APP_HTTP_MAX_BYTES" in config
        and "APP_READER_IMAGE_MAX_BYTES" in config,
        "Reader image configured size remains bounded by global HTTP response limit",
    ),
]

failed = []
for ok, label in checks:
    print(("PASS" if ok else "FAIL") + ": " + label)
    if not ok:
        failed.append(label)

if failed:
    raise SystemExit(f"{len(failed)}/{len(checks)} V1.38-D Reader Image Proxy contract checks failed.")

print(f"V1.38-D Reader Image Proxy contract checks: {len(checks)} passed.")
