# RSS Reader Modernization 1.38.0

V1.38.0 adds Reader Mode for focused RSS reading, safe on-demand Full Text retrieval, sanitized article extraction with Japanese charset normalization, and a same-origin Reader Image Proxy that minimizes automatic browser-direct requests to article image hosts.

## Main changes

### Reader Mode

- Open an authenticated Reader modal from RSS items without replacing the existing feed-card experience.
- Prefer RSS `content` and then `description` for the initial Reader body.
- Keep RSS Reader rendering text-safe and preserve the existing article link for explicit navigation.

### On-demand Full Text

- Add a Full Text action that resolves the target article from the authenticated owner's `content_id` and item identity; the client does not submit an arbitrary article URL for fetching.
- Fetch the article from the rental-server side through the existing hardened HTTP transport.
- Keep HTTP(S)-only validation, SSRF/private and reserved-address rejection, DNS pinning, redirect revalidation, TLS verification, timeout controls, response-size caps, and bounded stale behavior.
- Cache Full Text privately and fall back to the RSS body when fetching or extraction cannot safely complete.
- Full Text failures remain separate from Feed Health.

### Extraction, sanitization and charset handling

- Extract the main article region with lightweight DOM-based scoring and a body fallback.
- Allow a limited reading-oriented HTML subset and remove scripts, styles, frames, forms, embedded media, SVG and other active content.
- Resolve safe relative links and image sources against the effective article URL.
- Normalize article bytes to UTF-8 before DOM parsing using BOM, HTTP charset, HTML meta charset and bounded detection fallback, including common Japanese encodings.
- Split outbound Feed and Reader User-Agent settings into `APP_FEED_USER_AGENT` and `APP_READER_USER_AGENT`; existing `APP_HTTP_USER_AGENT` remains the fallback so existing installations do not require a new setting.

### Reader Image Proxy

- Rewrite Full Text raster-image sources to authenticated same-origin `reader_image.php?id=...` URLs.
- Expose only a user-bound opaque HMAC token to the browser; the source image URL remains in private server-side registry data outside `public/`.
- Fetch images through the same hardened outbound boundary used by the application.
- Allowlist JPEG, PNG/APNG, GIF, WebP and AVIF responses, validate image signatures, reject SVG, and apply a bounded per-image response limit.
- Cache validated images privately with checksum validation, expiry, symlink rejection and atomic replacement.
- If an image cannot be safely registered or fetched, do not fall back to the original remote `src`.
- Article links remain direct and clickable; external article navigation therefore occurs only after an explicit user action.

## Database upgrade

No database migration is required for V1.38.0.

Existing RSS, content, stock, user and Dashboard data remain unchanged.

## Configuration

No new setting is required for an existing installation.

Optional Reader/Feed User-Agent overrides are available through `APP_FEED_USER_AGENT` and `APP_READER_USER_AGENT`. If they are not set, the existing `APP_HTTP_USER_AGENT` value is used.

Reader Full Text and image cache/proxy settings have safe defaults and use private server-side storage outside the public document root.

## Security and compatibility

- Existing authentication, session, owner scope, CSRF, validation, output escaping and Feed Security boundaries remain in place.
- Full Text does not accept an arbitrary remote article URL from the browser.
- Reader image requests do not accept a remote image URL from the browser.
- Image proxy tokens are bound to the authenticated user and source URL.
- Automatic article-image retrieval is routed through the RSS Reader server; explicit article-link clicks still navigate directly to the external article.
- No headless browser, JavaScript execution engine, paywall bypass, CAPTCHA bypass, new credential, or external dependency is introduced.

## Verification completed

- Production verification completed for Reader Mode and Full Text retrieval.
- Production verification confirmed the charset normalization fix on an article that previously displayed mojibake.
- Production verification completed for the same-origin Reader Image Proxy.
- V1.38-D runtime coverage includes 34 dedicated checks, with 18 additional security/static contract checks.
- Dedicated tests cover user/token isolation, token tampering, cache hit/stale behavior, MIME rejection, file-signature mismatch, SVG rejection, private-address redirect rejection, response-size failure, checksum tampering, symlink rejection, mapping expiry, Apache endpoint rules and fail-safe behavior.
- Reader extraction tests cover same-origin image rewriting, source URL removal, article-link preservation and image-removal fail-safe.
- Current CI runs the complete regression gate on PHP 8.1 and PHP 8.4.

## Verification limits

- Some sites that depend on client-side JavaScript, unusual markup, authentication, paywalls, CAPTCHA or unsupported response formats may still fall back to RSS content.
- SVG and non-allowlisted image formats are intentionally not proxied.
- Clicking an article link intentionally leaves the RSS Reader and creates a direct browser request to that external site.
- Network visibility depends on the managed-device and network environment; the Reader Image Proxy minimizes automatic direct image requests but is not a general-purpose anonymity or traffic-hiding mechanism.
- The final PHP 8.1 and PHP 8.4 CI and Release workflow must pass before the immutable tag and release assets are considered complete.
- The Release workflow does not automatically deploy the formal package to the production environment.

## Release assets

The Release workflow publishes:

- `rss-reader-modernization-1.38.0.zip`
- `rss-reader-modernization-1.38.0.zip.sha256`
- `rss-reader-modernization-1.38.0-complete.zip`
- `rss-reader-modernization-1.38.0-complete.zip.sha256`
